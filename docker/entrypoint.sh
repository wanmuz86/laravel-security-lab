#!/bin/sh
set -e
cd /var/www/html

# Rebuild .env from .env.lab on every start (so edits to docker/env.lab on the host take effect
# after `docker compose restart app`), keeping an existing APP_KEY.
KEY=""
if [ -f .env ]; then
    KEY=$(grep '^APP_KEY=' .env | cut -d= -f2- || true)
fi
cp .env.lab .env
if [ -n "$KEY" ]; then
    sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|" .env
else
    php artisan key:generate --force
fi
php artisan config:clear >/dev/null 2>&1 || true

# Lab 8: composer.json / composer.lock are mounted from the repository. Sync vendor/ with the lock
# (a no-op when already in sync). Locked versions with advisories are installed as-is; auditing is a lab step.
composer install --no-interaction --no-progress \
    || echo "WARNING: composer install failed (offline?). vendor/ may not match composer.lock."

mkdir -p database storage/app/private storage/framework/{cache,sessions,views} storage/logs
touch database/database.sqlite

# Rebuild the database and seed training data on every container start
php artisan migrate:fresh --seed --force

echo "------------------------------------------------------------"
echo " SecureProject training lab running on http://localhost:8000"
echo " Test accounts: see README.md (password: Lab-Passw0rd!)"
echo " WARNING: intentionally vulnerable - local training use only"
echo "------------------------------------------------------------"

exec php artisan serve --host=0.0.0.0 --port=8000
