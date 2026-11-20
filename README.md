# FTP/SFTP Backup Script

A small PHP CLI tool to upload a local file to an **FTP** or **SFTP** server. It reads connection settings from a local `.env` file, creates the remote backup directory when possible, and uploads the file with a timestamped name.

## Features

- Upload a single local file over **FTP** or **SFTP** (selected via `.env` or command-line argument)
- Server address, port, username, password, and remote directory stored in `.env`
- Remote filename prefixed with the current time in `Asia/Kolkata` (e.g. `2026-11-20_19-37-51_backup.tar.gz`)
- Attempts to create missing remote directories automatically (`/BACKUP` by default)
- SFTP host key auto-scan via `ssh-keyscan` when no `SFTP_KNOWN_HOSTS` file is configured
- `.env` ignored by git to help keep credentials private

## Project files

| File | Purpose |
| --- | --- |
| `backup.php` | Main CLI backup script |
| `.env.example` | Template for server/configuration variables |
| `.env` | Local configuration and credentials (**do not commit**) |
| `setup.sh` | Dependency/setup helper for apt-based systems |
| `composer.json` | PHP project metadata (no external PHP packages required) |

## Requirements

- **PHP 8.1+** (CLI)
- **PHP cURL extension** (`php-curl`) with FTP and SFTP protocol support in the underlying libcurl
- Network access to your FTP/SFTP server
- For SFTP host key auto-scan (optional but helpful): `ssh-keyscan` available on `PATH`
- For `setup.sh` on Debian/Ubuntu: `apt-get` and `sudo` (when not running as root)

## Setup

### Option A: Automated setup (apt-based systems)

```sh
./setup.sh
```

`setup.sh` will:
1. Install PHP/cURL dependencies (`php-cli`, `php-curl`, `php-mbstring`, `curl`, `ca-certificates`) via `apt-get` when available
2. Verify that `php` and the cURL extension are available
3. Create `.env` from `.env.example` if `.env` does not already exist
4. Print next steps

> Note: `setup.sh` does not fill in your server credentials. You must edit `.env` manually.

### Option B: Manual setup

1. Install PHP and the cURL extension. Examples:
   - Debian/Ubuntu: `sudo apt-get install php-cli php-curl php-mbstring`
2. Create your env file if needed:
   ```sh
   cp .env.example .env
   ```
3. Edit `.env` with your FTP/SFTP server details.
4. Restrict permissions on `.env` (recommended):
   ```sh
   chmod 600 .env
   ```

## Configuration (`.env`)

`backup.php` loads variables from `.env` in the project directory at startup. Existing environment variables are **not** overwritten, so you can also provide values via the shell environment. Lines starting with `#` are comments; quoted values (`"..."` or `'...'`) are supported.

### Common

| Variable | Description | Default |
| --- | --- | --- |
| `BACKUP_PROTOCOL` | Protocol used when no protocol argument is passed: `ftp` or `sftp` | `sftp` (if unset) |

### FTP settings

| Variable | Description | Default |
| --- | --- | --- |
| `FTP_HOST` | FTP server hostname or IP address (**required** when using `ftp`) | *(none)* |
| `FTP_PORT` | FTP server port | `21` |
| `FTP_USER` | FTP username | *(empty)* |
| `FTP_PASS` | FTP password | *(empty)* |
| `FTP_REMOTE_DIR` | Remote directory to upload into | `/BACKUP` |

### SFTP settings

| Variable | Description | Default |
| --- | --- | --- |
| `SFTP_HOST` | SFTP server hostname or IP address (**required** when using `sftp`) | *(none)* |
| `SFTP_PORT` | SFTP server port | `22` |
| `SFTP_USER` | SFTP username | *(empty)* |
| `SFTP_PASS` | SFTP password | *(empty)* |
| `SFTP_REMOTE_DIR` | Remote directory to upload into | `/BACKUP` |
| `SFTP_KNOWN_HOSTS` | Path to a `known_hosts` file for host key verification | *(auto-scan when empty)* |

### Example `.env`

```dotenv
BACKUP_PROTOCOL=sftp

# FTP server settings
FTP_HOST=ftp.example.com
FTP_PORT=21
FTP_USER=myftpuser
FTP_PASS=myftppassword
FTP_REMOTE_DIR=/BACKUP

# SFTP server settings
SFTP_HOST=sftp.example.com
SFTP_PORT=22
SFTP_USER=mysftpuser
SFTP_PASS=mysftppassword
SFTP_REMOTE_DIR=/BACKUP
# Optional: path to a known_hosts file. If empty, host key is scanned automatically when possible.
SFTP_KNOWN_HOSTS=
```

## Usage

```sh
php backup.php <local-file> [ftp|sftp]
```

- `<local-file>`: path to an existing, readable local file to upload.
- `[ftp|sftp]` (optional): protocol override. If omitted, `BACKUP_PROTOCOL` from `.env`/environment is used.

### Examples

```sh
# Use BACKUP_PROTOCOL from .env
php backup.php ./test.txt

# Force FTP
php backup.php ./test.txt ftp

# Force SFTP
php backup.php /var/backups/site.tar.gz sftp
```

### What the script does

1. Validates arguments and checks that the local file exists and is readable.
2. Determines protocol (`ftp` or `sftp`) and loads the corresponding `FTP_*` or `SFTP_*` settings.
3. Builds a remote filename as `<timestamp>_<original-filename>` using the current time in the `Asia/Kolkata` timezone.
4. Normalizes the remote directory (uses `/BACKUP` by default) and uploads to `<remote-dir>/<timestamped-filename>`.
5. Attempts to create missing remote directories automatically.
6. Prints protocol, upload target, remote filename, and local size on success.

### Successful output (example)

```text
Protocol: sftp
Uploaded to: sftp://sftp.example.com:22/BACKUP/2026-11-21_10-15-30_test.txt
Remote file: 2026-11-21_10-15-30_test.txt
Local size: 12345 bytes
```

## SFTP host key verification

When using SFTP:
- If `SFTP_KNOWN_HOSTS` is set, that file is passed to cURL for host key verification.
- If `SFTP_KNOWN_HOSTS` is empty, `backup.php` attempts to scan the server key with `ssh-keyscan -p <port> -T 5 <host>` and uses a temporary known-hosts file for the transfer.
- If auto-scan is not possible and the upload fails with a host key/verification error, add the host key manually and/or set `SFTP_KNOWN_HOSTS`, for example:

```sh
mkdir -p ~/.ssh
ssh-keyscan -p 22 sftp.example.com >> ~/.ssh/known_hosts
# then set SFTP_KNOWN_HOSTS=/home/<user>/.ssh/known_hosts in .env
```

## Troubleshooting

- **`Error: <PROTOCOL>_HOST is not set in .env or environment.`**  
  Set `FTP_HOST` or `SFTP_HOST` (matching the protocol you are using) in `.env` or export it in your shell.
- **`Error: protocol must be 'ftp' or 'sftp'.`**  
  Use `ftp` or `sftp` as the optional second argument, or set `BACKUP_PROTOCOL` accordingly.
- **`Error: file does not exist or is not readable: ...`**  
  Check the local file path and permissions.
- **`Error: PHP curl extension is not enabled...` / cURL init errors**  
  Install/enable `php-curl` and ensure your PHP/libcurl build supports FTP/SFTP.
- **`Backup failed (ftp|sftp): ...`**  
  Verify host, port, username/password, network/firewall access, and remote directory permissions. The script prints the target URL and cURL error details. For SFTP host key issues, see [SFTP host key verification](#sftp-host-key-verification).
- **Remote directory not created**  
  Automatic directory creation depends on server/libcurl support and your account permissions; create the directory on the server manually if needed.

## Security notes

- `.env` contains credentials and is excluded by `.gitignore`—**do not commit or share it**.
- Prefer strong, unique credentials and restrict `.env` permissions (e.g. `chmod 600 .env`).
- Use trusted servers and secure networks. FTP transmits credentials/data without SSH protection; prefer **SFTP** when available.

## Exit codes

- `0`: upload completed successfully
- `1`: runtime/transfer failure
- `2`: usage or input/configuration error
