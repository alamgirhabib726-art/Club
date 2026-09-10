#!/bin/bash
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:$PATH"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

TARGET_PORT="3000"
if [ -n "$RAILWAY_ENVIRONMENT" ] && [ -n "$PORT" ]; then
    TARGET_PORT="$PORT"
fi

# Ensure database is bootstrapped if database.sqlite is empty
if [ ! -s "database.sqlite" ]; then
    if command -v node > /dev/null 2>&1; then
        node db.js > /dev/null 2>&1 || true
    fi
fi

# Run PHP built-in server on target port with multi-worker concurrency
export PHP_CLI_SERVER_WORKERS=4
echo "Starting PHP server on port ${TARGET_PORT} with 4 workers..."
exec php -S 0.0.0.0:${TARGET_PORT} -t htdocs router.php
