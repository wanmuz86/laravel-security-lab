FROM php:8.3-cli

# System packages + PHP extensions needed by Laravel (SQLite keeps the lab to a single container)
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libzip-dev libsqlite3-dev libonig-dev libpng-dev libjpeg-dev \
    && docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install pdo_sqlite zip mbstring gd \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Fresh Laravel skeleton (pinned major version for a reproducible lab)
RUN composer create-project laravel/laravel:^12.0 . --prefer-dist --no-interaction

# Lab 2: Pest for automated security tests (pinned to the PHPUnit 11 line used by Laravel 12)
RUN composer config --no-plugins allow-plugins.pestphp/pest-plugin true \
    && composer require --dev pestphp/pest:^3.8 pestphp/pest-plugin-laravel:^3.2 \
        --with-all-dependencies --no-interaction

# Lab 4: Laravel Sanctum (personal access tokens). Its migration is shipped in database/migrations.
RUN composer require laravel/sanctum:^4.0 --no-interaction

# The skeleton ships its own users migration and example tests that clash with the lab code
RUN rm -f database/migrations/0001_01_01_000000_create_users_table.php \
          tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php

# Overlay the SecureProject application code on top of the skeleton.
# (docker-compose.yml also bind-mounts the editable folders so participants can inspect/edit them from the host.)
COPY bootstrap/app.php bootstrap/app.php
COPY config/ config/
COPY public/css/ public/css/
COPY public/js/ public/js/
COPY docker/php/lab5.ini /usr/local/etc/php/conf.d/zz-lab5.ini
COPY phpunit.xml phpunit.xml
COPY app/ app/
COPY routes/ routes/
COPY resources/views/ resources/views/
COPY database/migrations/ database/migrations/
COPY database/seeders/ database/seeders/
COPY database/factories/ database/factories/
COPY tests/ tests/
COPY docker/env.lab .env.lab
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8000
ENTRYPOINT ["entrypoint.sh"]
