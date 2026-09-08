FROM php:8.2-cli

# Install system dependencies including MariaDB server and client, and required PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    mariadb-client \
    sqlite3 \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    curl \
    git \
    unzip \
    bash \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    pdo_sqlite \
    mysqli \
    mbstring \
    gd \
    zip \
    bcmath \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . /app

# Ensure directories exist and have proper permissions
RUN mkdir -p /var/lib/mysql /var/run/mysqld /app/htdocs/uploads /app/htdocs/chat/uploads/chat \
    && chown -R mysql:mysql /var/lib/mysql /var/run/mysqld \
    && chmod -R 777 /app/htdocs/uploads /app/htdocs/chat/uploads \
    && chmod +x /app/run.sh

ENV PORT=3000
EXPOSE 3000

CMD ["bash", "/app/run.sh"]
