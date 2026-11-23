#!/usr/bin/env bash
# Docker-free setup for Labs 1-4 (fallback when Docker is too slow on a laptop).
# Requires: PHP >= 8.2 with pdo_sqlite, mbstring, zip, tokenizer, xml, ctype; Composer 2.
# Mirrors the Dockerfile: Laravel 12 skeleton + Pest + Sanctum, lab code overlaid on top.
set -euo pipefail
cd "$(dirname "$0")/.."

for bin in php composer; do
  command -v "$bin" >/dev/null || { echo "Missing: $bin"; exit 1; }
done
php -r 'exit(version_compare(PHP_VERSION,"8.2.0",">=")?0:1);' || { echo "PHP 8.2+ required"; exit 1; }
for ext in pdo_sqlite mbstring zip; do
  php -m | grep -qix "$ext" || { echo "Missing PHP extension: $ext"; exit 1; }
done

if [ ! -f vendor/autoload.php ]; then
  TMP="$(mktemp -d)"
  composer create-project laravel/laravel:^12.0 "$TMP/app" --prefer-dist --no-interaction
  # Copy skeleton files WITHOUT overwriting the lab's own files
  cp -Rn "$TMP/app/." .
  rm -rf "$TMP"
  composer config --no-plugins allow-plugins.pestphp/pest-plugin true
  composer require --dev pestphp/pest:^3.8 pestphp/pest-plugin-laravel:^3.2 \
    --with-all-dependencies --no-interaction
  composer require laravel/sanctum:^4.0 --no-interaction
fi

# Skeleton files that clash with the lab code
rm -f database/migrations/0001_01_01_000000_create_users_table.php \
      tests/Feature/ExampleTest.php tests/Unit/ExampleTest.php

# Local .env from the lab template (DB path made relative to this checkout)
KEY=""
[ -f .env ] && KEY=$(grep '^APP_KEY=' .env | cut -d= -f2- || true)
sed "s|^DB_DATABASE=.*|DB_DATABASE=$(pwd)/database/database.sqlite|" docker/env.lab > .env
[ -n "$KEY" ] && sed -i.bak "s|^APP_KEY=.*|APP_KEY=$KEY|" .env && rm -f .env.bak
[ -n "$KEY" ] || php artisan key:generate --force

mkdir -p storage/app/private storage/framework/{cache,sessions,views} storage/logs
touch database/database.sqlite
php artisan config:clear >/dev/null 2>&1 || true
php artisan migrate:fresh --seed --force

echo "------------------------------------------------------------"
echo " Ready. Start the app:  php artisan serve   (http://localhost:8000)"
echo " Run tests:             php artisan test"
echo " Reset data:            php artisan migrate:fresh --seed --force"
echo " Test accounts: see README.md (password: Lab-Passw0rd!)"
echo "------------------------------------------------------------"
