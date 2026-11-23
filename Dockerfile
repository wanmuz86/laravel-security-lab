FROM php:8.3-cli

# System packages + PHP extensions needed by Laravel (SQLite keeps the lab to a single container)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libsqlite3-dev libonig-dev \
    && docker-php-ext-install pdo_sqlite zip mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Fresh Laravel skeleton (pinned major version for a reproducible lab)
RUN composer create-project laravel/laravel:^12.0 . --prefer-dist --no-interaction

# Overlay the SecureProject application code on top of the skeleton.
# (docker-compose.yml also bind-mounts the editable folders so participants can inspect/edit them from the host.)
COPY bootstrap/app.php bootstrap/app.php
COPY app/ app/
COPY routes/ routes/
COPY resources/views/ resources/views/
COPY database/migrations/ database/migrations/
COPY database/seeders/ database/seeders/
COPY tests/ tests/
COPY docker/env.lab .env.lab
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["entrypoint.sh"]
