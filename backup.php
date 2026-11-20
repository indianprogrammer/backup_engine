<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(2);
}

/**
 * Load KEY=value pairs from a .env file into the process environment.
 * Existing environment variables are not overwritten.
 */
function loadEnvFile(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        if ($key === '') {
            continue;
        }
        $value = trim(substr($line, $pos + 1));
        if ($value !== '' && (
            ($value[0] === '"' && substr($value, -1) === '"') ||
            ($value[0] === "'" && substr($value, -1) === "'")
        )) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        return $default;
    }
    return $value;
}

function usage(): void
{
    fwrite(STDERR, "Usage: php backup.php <local-file> [ftp|sftp]\n");
    fwrite(STDERR, "  Protocol defaults to BACKUP_PROTOCOL from .env (ftp or sftp).\n");
}

loadEnvFile(__DIR__ . '/.env');

if ($argc < 2 || $argc > 3) {
    usage();
    exit(2);
}

$localArg = $argv[1];
$protocolArg = $argv[2] ?? env('BACKUP_PROTOCOL', 'sftp');
$protocol = strtolower(trim((string) $protocolArg));

if (!in_array($protocol, ['ftp', 'sftp'], true)) {
    fwrite(STDERR, "Error: protocol must be 'ftp' or 'sftp'.\n");
    usage();
    exit(2);
}

$localPath = realpath($localArg);
if ($localPath === false || !is_file($localPath) || !is_readable($localPath)) {
    fwrite(STDERR, "Error: file does not exist or is not readable: {$localArg}\n");
    exit(2);
}

$fileSize = filesize($localPath);
if ($fileSize === false) {
    fwrite(STDERR, "Error: could not determine the file size: {$localPath}\n");
    exit(2);
}

$prefix = strtoupper($protocol);
$host = env($prefix . '_HOST');
$port = (int) (env($prefix . '_PORT', $protocol === 'sftp' ? '22' : '21') ?? ($protocol === 'sftp' ? '22' : '21'));
$user = env($prefix . '_USER', '');
$pass = env($prefix . '_PASS', '');
$remoteDir = env($prefix . '_REMOTE_DIR', '/BACKUP') ?? '/BACKUP';

if ($host === null || $host === '') {
    fwrite(STDERR, "Error: {$prefix}_HOST is not set in .env or environment.\n");
    exit(2);
}

$localName = basename($localPath);
$timestamp = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))
    ->format('Y-m-d_H-i-s');
$remoteName = "{$timestamp}_{$localName}";

$remoteDir = '/' . trim(str_replace('\\', '/', $remoteDir), '/');
$remoteDirPath = $remoteDir === '/' ? '' : $remoteDir;
$remotePath = ($remoteDirPath === '' ? '' : $remoteDirPath) . '/' . $remoteName;
$remotePath = '/' . ltrim($remotePath, '/');

$url = sprintf('%s://%s:%d%s', $protocol, $host, $port, $remotePath);

$fp = fopen($localPath, 'rb');
if ($fp === false) {
    fwrite(STDERR, "Error: could not open file for reading: {$localPath}\n");
    exit(2);
}

$knownHostsFile = null;
$tempKnownHosts = null;
if ($protocol === 'sftp') {
    $configuredKnownHosts = env($prefix . '_KNOWN_HOSTS');
    if ($configuredKnownHosts !== null && $configuredKnownHosts !== '') {
        $knownHostsFile = $configuredKnownHosts;
    } else {
        $scan = shell_exec(
            'ssh-keyscan -p ' . escapeshellarg((string) $port) . ' -T 5 ' . escapeshellarg($host) . ' 2>/dev/null'
        );
        if (is_string($scan) && trim($scan) !== '') {
            $tempKnownHosts = tempnam(sys_get_temp_dir(), 'known_hosts_');
            if ($tempKnownHosts !== false && file_put_contents($tempKnownHosts, $scan) !== false) {
                $knownHostsFile = $tempKnownHosts;
            } elseif ($tempKnownHosts !== false) {
                @unlink($tempKnownHosts);
                $tempKnownHosts = null;
            }
        }
    }
}

$ch = curl_init();
if ($ch === false) {
    fclose($fp);
    if ($tempKnownHosts !== null && is_file($tempKnownHosts)) {
        @unlink($tempKnownHosts);
    }
    fwrite(STDERR, "Error: failed to initialize curl.\n");
    exit(1);
}

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_UPLOAD, true);
curl_setopt($ch, CURLOPT_INFILE, $fp);
curl_setopt($ch, CURLOPT_INFILESIZE, $fileSize);
curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
curl_setopt($ch, CURLOPT_FTP_CREATE_MISSING_DIRS, CURLFTP_CREATE_DIR);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
curl_setopt($ch, CURLOPT_TIMEOUT, 0);
curl_setopt($ch, CURLOPT_PROTOCOLS, $protocol === 'sftp' ? CURLPROTO_SFTP : (CURLPROTO_FTP | CURLPROTO_FTPS));
curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, $protocol === 'sftp' ? CURLPROTO_SFTP : (CURLPROTO_FTP | CURLPROTO_FTPS));

if ($protocol === 'sftp' && $knownHostsFile !== null && $knownHostsFile !== '') {
    curl_setopt($ch, CURLOPT_SSH_KNOWNHOSTS, $knownHostsFile);
}

$ok = curl_exec($ch);
$curlErr = $ok ? '' : curl_error($ch);
$curlErrNo = curl_errno($ch);
curl_close($ch);
fclose($fp);

if ($tempKnownHosts !== null && is_file($tempKnownHosts)) {
    @unlink($tempKnownHosts);
}

if (!$ok) {
    fwrite(STDERR, "Backup failed ({$protocol}): [{$curlErrNo}] {$curlErr}\n");
    fwrite(STDERR, "Target: {$protocol}://{$host}:{$port}{$remotePath}\n");
    if ($protocol === 'sftp' && $knownHostsFile === null) {
        fwrite(STDERR, "Hint: could not auto-scan host key; set SFTP_KNOWN_HOSTS in .env or add the host to ~/.ssh/known_hosts.\n");
    }
    exit(1);
}

printf("Protocol: %s\n", $protocol);
printf("Uploaded to: %s://%s:%d%s\n", $protocol, $host, $port, $remotePath);
printf("Remote file: %s\n", $remoteName);
printf("Local size: %d bytes\n", $fileSize);
