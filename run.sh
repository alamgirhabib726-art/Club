#!/bin/bash
set -e

# Ensure MariaDB datadir exists and is initialized
if [ ! -d "/var/lib/mysql/mysql" ]; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql || true
fi

# Ensure MariaDB server is running
if ! pgrep -x "mariadbd" > /dev/null 2>&1; then
    echo "Starting MariaDB daemon..."
    su -s /bin/bash mysql -c "mariadbd --datadir=/var/lib/mysql" &
    sleep 2
fi

# Ensure database schema is loaded
if ! mariadb -u root -e "USE if0_40736960_club;" > /dev/null 2>&1; then
    echo "Importing database schema..."
    mariadb --default-character-set=utf8mb4 -u root < database.sql || true
fi

# Run PHP built-in server
echo "Starting PHP server on port 3000..."
exec php -S 0.0.0.0:3000 -t htdocs router.php
