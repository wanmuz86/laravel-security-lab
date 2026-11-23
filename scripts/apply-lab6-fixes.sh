#!/bin/sh
# Instructor/after-lab helper for Lab 6: copies the reference solution from ./solutions/lab6,
# configures the environment (allowlist, random webhook secret, database cache), restarts the app
# and runs the SSRF and webhook tests.
# Run from the project root on the HOST. Uses `docker compose up -d` so the new bind mounts apply.
set -e

S=solutions/lab6
ENV=docker/env.lab

mkdir -p app/Services app/Http/Requests app/Http/Middleware tests/Unit tests/Feature/Security

cp $S/SafeUrlValidator.php        app/Services/SafeUrlValidator.php
cp $S/SecureHttpFetcher.php       app/Services/SecureHttpFetcher.php
cp $S/FetchUrlRequest.php         app/Http/Requests/FetchUrlRequest.php
cp $S/SecureFetchController.php   app/Http/Controllers/SecureFetchController.php
cp $S/WebhookController.php       app/Http/Controllers/WebhookController.php
cp $S/VerifyWebhookSignature.php  app/Http/Middleware/VerifyWebhookSignature.php
cp $S/PreventWebhookReplay.php    app/Http/Middleware/PreventWebhookReplay.php
cp $S/integrations.php            config/integrations.php
cp $S/2026_01_01_000009_create_cache_table.php database/migrations/2026_01_01_000009_create_cache_table.php
cp $S/web.php                     routes/web.php
cp $S/api.php                     routes/api.php
cp $S/bootstrap-app.php           bootstrap/app.php
cp $S/tests/SafeUrlValidatorTest.php tests/Unit/SafeUrlValidatorTest.php
cp $S/tests/SsrfSecurityTest.php     tests/Feature/Security/SsrfSecurityTest.php
cp $S/tests/WebhookSecurityTest.php  tests/Feature/Security/WebhookSecurityTest.php

# docker/env.lab: set KEY=VALUE, replacing any existing line
set_env() {
    [ -n "$(tail -c1 "$ENV")" ] && echo >> "$ENV"
    grep -v "^$1=" "$ENV" > "$ENV.tmp" || true
    echo "$1=$2" >> "$ENV.tmp"
    mv "$ENV.tmp" "$ENV"
}

grep -q '^FETCH_ALLOWED_HOSTS=.' "$ENV" || set_env FETCH_ALLOWED_HOSTS example.com
if ! grep -q '^WEBHOOK_SHARED_SECRET=.\{32,\}' "$ENV"; then
    SECRET=$(openssl rand -hex 32 2>/dev/null || LC_ALL=C tr -dc 'a-f0-9' < /dev/urandom | head -c 64)
    set_env WEBHOOK_SHARED_SECRET "$SECRET"
fi
set_env CACHE_STORE database

docker compose up -d
sleep 10
docker compose exec app php artisan test tests/Unit/SafeUrlValidatorTest.php tests/Feature/Security/SsrfSecurityTest.php tests/Feature/Security/WebhookSecurityTest.php
