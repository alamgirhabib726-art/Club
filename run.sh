#!/bin/bash
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

TARGET_PORT="${PORT:-3000}"

# Check if an external MySQL database is provided (Railway / Cloud MySQL)
if [ -n "$MYSQLHOST" ] || [ -n "$MYSQL_URL" ] || [ -n "$DATABASE_URL" ] || [ -n "$DB_HOST" ]; then
    echo "External MySQL detected. Skipping local MariaDB daemon initialization."
else
    # Ensure local MariaDB datadir exists and is initialized
    mkdir -p /var/lib/mysql /var/run/mysqld
    chown -R mysql:mysql /var/lib/mysql /var/run/mysqld 2>/dev/null || true

    if command -v mariadb-install-db > /dev/null 2>&1; then
        if [ ! -d "/var/lib/mysql/mysql" ]; then
            echo "Initializing MariaDB database..."
            mariadb-install-db --user=mysql --datadir=/var/lib/mysql 2>/dev/null || mariadb-install-db --datadir=/var/lib/mysql 2>/dev/null || true
        fi
    elif command -v mysql_install_db > /dev/null 2>&1; then
        if [ ! -d "/var/lib/mysql/mysql" ]; then
            echo "Initializing MySQL database..."
            mysql_install_db --user=mysql --datadir=/var/lib/mysql 2>/dev/null || mysql_install_db --datadir=/var/lib/mysql 2>/dev/null || true
        fi
    fi

    # Ensure MariaDB server is running
    if ! pgrep -x "mariadbd" > /dev/null 2>&1 && ! pgrep -x "mysqld" > /dev/null 2>&1; then
        echo "Starting MariaDB daemon..."
        if command -v mariadbd > /dev/null 2>&1; then
            (mariadbd --user=mysql --datadir=/var/lib/mysql 2>/dev/null || su -s /bin/bash mysql -c "mariadbd --datadir=/var/lib/mysql" 2>/dev/null || mariadbd --datadir=/var/lib/mysql 2>/dev/null) &
        elif command -v mysqld > /dev/null 2>&1; then
            (mysqld --user=mysql --datadir=/var/lib/mysql 2>/dev/null || su -s /bin/bash mysql -c "mysqld --datadir=/var/lib/mysql" 2>/dev/null || mysqld --datadir=/var/lib/mysql 2>/dev/null) &
        elif [ -f /etc/init.d/mysql ] || [ -f /etc/init.d/mariadb ]; then
            service mariadb start 2>/dev/null || service mysql start 2>/dev/null || true
        fi
        sleep 2
    fi

    # Ensure database schema is loaded
    if command -v mariadb > /dev/null 2>&1; then
        if ! mariadb -u root -e "USE if0_40736960_club;" > /dev/null 2>&1; then
            echo "Importing database schema..."
            mariadb --default-character-set=utf8mb4 -u root < database.sql 2>/dev/null || true
        fi
    elif command -v mysql > /dev/null 2>&1; then
        if ! mysql -u root -e "USE if0_40736960_club;" > /dev/null 2>&1; then
            echo "Importing database schema..."
            mysql --default-character-set=utf8mb4 -u root < database.sql 2>/dev/null || true
        fi
    fi
fi

# Run PHP built-in server on target port
echo "Starting PHP server on port ${TARGET_PORT}..."
exec php -S 0.0.0.0:${TARGET_PORT} -t htdocs router.php
