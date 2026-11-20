#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

SUDO=""
if [ "$(id -u)" -ne 0 ]; then
  if command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
  else
    echo "Error: this script needs root privileges to install packages. Re-run as root or install sudo." >&2
    exit 1
  fi
fi

have() { command -v "$1" >/dev/null 2>&1; }

echo "==> FTP/SFTP backup setup"
echo "    Project: $SCRIPT_DIR"

if have apt-get; then
  echo "==> Updating package index..."
  $SUDO apt-get update -y

  echo "==> Installing PHP and curl dependencies..."
  $SUDO apt-get install -y \
    ca-certificates curl \
    php-cli php-curl php-mbstring \
    2>/dev/null || $SUDO apt-get install -y \
    ca-certificates curl php-cli php-curl
else
  echo "Warning: apt-get not found. Ensure PHP 8.1+ with the curl extension is installed manually." >&2
fi

if ! have php; then
  echo "Error: php is not installed or not on PATH." >&2
  exit 1
fi

echo "==> PHP: $(php -r 'echo PHP_VERSION;')"
if ! php -r 'exit(extension_loaded("curl") ? 0 : 1);'; then
  echo "Error: PHP curl extension is not enabled. Install/enable php-curl and re-run." >&2
  exit 1
fi

if [ ! -f "$SCRIPT_DIR/.env" ] && [ -f "$SCRIPT_DIR/.env.example" ]; then
  cp "$SCRIPT_DIR/.env.example" "$SCRIPT_DIR/.env"
  echo "==> Created .env from .env.example"
fi

echo
echo "==> Setup complete."
echo "    1. Edit .env with your FTP/SFTP server address, port, and credentials."
echo "    2. Run: php backup.php /path/to/local-file [ftp|sftp]"
echo "    Keep .env private (it contains credentials)."
