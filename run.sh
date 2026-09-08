#!/bin/bash
export PATH="/usr/bin:/bin:/usr/sbin:/sbin:/usr/local/bin:$PATH"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

TARGET_PORT="3000"

# Install php-cli and database tools if not present
if ! command -v php > /dev/null 2>&1; then
    echo "PHP not detected in PATH. Installing php-cli and extensions..."
    export DEBIAN_FRONTEND=noninteractive
    export DEBCONF_NONINTERACTIVE_SEEN=true
    apt-get update -qq > /dev/null 2>&1 || true
    apt-get install -y --no-install-recommends -o Dpkg::Options::="--force-confdef" -o Dpkg::Options::="--force-confold" php-cli php-mysql php-sqlite3 mariadb-server mariadb-client > /dev/null 2>&1 || true
fi

# Check if an external MySQL database is provided (Railway / Cloud MySQL)
if [ -n "$MYSQLHOST" ] || [ -n "$MYSQL_URL" ] || [ -n "$DATABASE_URL" ] || [ -n "$DB_HOST" ]; then
    echo "External MySQL configured."
else
    # Ensure local MariaDB datadir exists and is initialized
    mkdir -p /var/lib/mysql /var/run/mysqld 2>/dev/null || true
    chown -R mysql:mysql /var/lib/mysql /var/run/mysqld 2>/dev/null || true

    if command -v mariadb-install-db > /dev/null 2>&1; then
        if [ ! -d "/var/lib/mysql/mysql" ]; then
            echo "Initializing MariaDB database..."
            mariadb-install-db --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1 || true
        fi
    fi

    # Ensure MariaDB server is running in background
    if ! pgrep -x "mariadbd" > /dev/null 2>&1 && ! pgrep -x "mysqld" > /dev/null 2>&1; then
        if command -v mariadbd > /dev/null 2>&1; then
            (mariadbd --user=mysql --datadir=/var/lib/mysql > /dev/null 2>&1 || su -s /bin/bash mysql -c "mariadbd --datadir=/var/lib/mysql" > /dev/null 2>&1 || mariadbd --datadir=/var/lib/mysql > /dev/null 2>&1) &
        elif [ -f /etc/init.d/mariadb ] || [ -f /etc/init.d/mysql ]; then
            service mariadb start > /dev/null 2>&1 || service mysql start > /dev/null 2>&1 || true
        fi
    fi
fi

# Run PHP built-in server on target port 3000
echo "Starting PHP server on port ${TARGET_PORT}..."
exec php -S 0.0.0.0:${TARGET_PORT} -t htdocs router.php
