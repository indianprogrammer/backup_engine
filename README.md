# Backup a local file to an FTP or SFTP server

`backup.php` uploads one local file to an FTP or SFTP server. The remote filename
is prefixed with the current time in `Asia/Kolkata`, for example
`2026-11-20_19-37-51_backup.tar.gz`. Remote backup directories are created
automatically when possible.

## Setup

1. Install PHP 8.1+ with the `curl` extension (and `mbstring` recommended).
2. Configure server credentials in `.env` (see `.env.example`):

   - `BACKUP_PROTOCOL`: `ftp` or `sftp` (default when not passed on command line)
   - `FTP_HOST`, `FTP_PORT`, `FTP_USER`, `FTP_PASS`, `FTP_REMOTE_DIR` (default `/BACKUP`)
   - `SFTP_HOST`, `SFTP_PORT`, `SFTP_USER`, `SFTP_PASS`, `SFTP_REMOTE_DIR` (default `/BACKUP`)
   - Optional: `SFTP_KNOWN_HOSTS` path to a known_hosts file for SFTP host key verification

3. Keep `.env` private; it contains credentials and is ignored by git.

You can run `./setup.sh` on apt-based systems to install PHP/curl dependencies.

## Usage

```sh
php backup.php /path/to/local-file
php backup.php /path/to/local-file ftp
php backup.php /path/to/local-file sftp
```

If a protocol argument is provided, it overrides `BACKUP_PROTOCOL` from `.env`.
The file is uploaded to the configured `*_REMOTE_DIR` on the selected server.
