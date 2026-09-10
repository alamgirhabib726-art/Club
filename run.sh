#!/bin/bash
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:$PATH"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

TARGET_PORT="3000"
if [ -n "$RAILWAY_ENVIRONMENT" ] && [ -n "$PORT" ]; then
    TARGET_PORT="$PORT"
fi

# Automatically ensure PHP runtime is installed
if ! command -v php > /dev/null 2>&1 && ! [ -x "/usr/bin/php" ] && ! [ -x "/usr/bin/php8.2" ]; then
    echo "PHP runtime not found. Installing PHP CLI and extensions..."
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq && apt-get install -y -qq -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" php-cli php-sqlite3 php-curl php-mbstring > /dev/null 2>&1 || true
fi

# Determine PHP binary
PHP_BIN="php"
if command -v php > /dev/null 2>&1; then
    PHP_BIN="$(command -v php)"
elif [ -x "/usr/bin/php8.2" ]; then
    PHP_BIN="/usr/bin/php8.2"
elif [ -x "/usr/bin/php" ]; then
    PHP_BIN="/usr/bin/php"
fi

# Ensure database is bootstrapped if database.sqlite is empty
if [ ! -s "database.sqlite" ]; then
    if command -v node > /dev/null 2>&1; then
        node db.js > /dev/null 2>&1 || true
    fi
fi

# Run PHP built-in server on target port with multi-worker concurrency
export PHP_CLI_SERVER_WORKERS=4
echo "Starting PHP server on port ${TARGET_PORT} with 4 workers using ${PHP_BIN}..."
exec "$PHP_BIN" -S 0.0.0.0:${TARGET_PORT} -t htdocs router.php
