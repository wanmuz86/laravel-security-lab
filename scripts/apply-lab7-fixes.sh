#!/bin/sh
# Instructor/after-lab helper for Lab 7: copies the reference solution from ./solutions/lab7,
# sets APP_ENV=production / APP_DEBUG=false in docker/env.lab (Part G1), restarts the app
# (the container rebuilds and reseeds the database, creating the new tables) and runs the tests.
# Run from the project root on the HOST.
set -e

S=solutions/lab7
ENV=docker/env.lab

mkdir -p app/Services app/Policies app/Http/Resources app/Http/Requests app/Http/Controllers/Api tests/Feature/Security

cp $S/Order.php                      app/Models/Order.php
cp $S/OrderProcessing.php            app/Models/OrderProcessing.php
cp $S/OrderPolicy.php                app/Policies/OrderPolicy.php
cp $S/UpdateOrderRequest.php         app/Http/Requests/UpdateOrderRequest.php
cp $S/OrderResource.php              app/Http/Resources/OrderResource.php
cp $S/OrderController.php            app/Http/Controllers/Api/OrderController.php
cp $S/OrderProcessingController.php  app/Http/Controllers/Api/OrderProcessingController.php
cp $S/OrderProcessingService.php     app/Services/OrderProcessingService.php
cp $S/SecurityEventLogger.php        app/Services/SecurityEventLogger.php
cp $S/AppServiceProvider.php         app/Providers/AppServiceProvider.php
cp $S/2026_01_01_000011_create_order_processings_table.php database/migrations/2026_01_01_000011_create_order_processings_table.php
cp $S/api.php                        routes/api.php
cp $S/bootstrap-app.php              bootstrap/app.php
cp $S/tests/*.php                    tests/Feature/Security/

# docker/env.lab: set KEY=VALUE, replacing any existing line
set_env() {
    [ -n "$(tail -c1 "$ENV")" ] && echo >> "$ENV"
    grep -v "^$1=" "$ENV" > "$ENV.tmp" || true
    echo "$1=$2" >> "$ENV.tmp"
    mv "$ENV.tmp" "$ENV"
}
set_env APP_ENV production
set_env APP_DEBUG false

docker compose restart app
sleep 10
docker compose exec app php artisan test tests/Feature/Security/OrderApiSecurityTest.php tests/Feature/Security/OrderRateLimitTest.php tests/Feature/Security/ApiErrorHandlingTest.php
