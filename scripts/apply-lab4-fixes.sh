#!/bin/sh
# Instructor/after-lab helper for Lab 4: copies the reference solution from ./solutions/lab4,
# restarts the app so the hardened environment applies, and runs the security tests.
# Run from the project root on the HOST. Requires a rebuilt image (docker compose up -d --build).
set -e

S=solutions/lab4
mkdir -p app/Http/Controllers/Auth app/Http/Controllers/Api app/Http/Requests/Auth tests/Feature/Security

cp $S/LoginController.php          app/Http/Controllers/Auth/LoginController.php
cp $S/PasswordResetController.php  app/Http/Controllers/Auth/PasswordResetController.php
cp $S/Api/TokenController.php      app/Http/Controllers/Api/TokenController.php
cp $S/Api/ProjectApiController.php app/Http/Controllers/Api/ProjectApiController.php
cp $S/LoginRequest.php             app/Http/Requests/Auth/LoginRequest.php
cp $S/AuthController.php           app/Http/Controllers/AuthController.php
cp $S/web.php                      routes/web.php
cp $S/api.php                      routes/api.php
cp $S/bootstrap-app.php            bootstrap/app.php
cp $S/auth.php                     config/auth.php
cp $S/env.lab.hardened             docker/env.lab
cp $S/tests/*.php                  tests/Feature/Security/

docker compose restart app
sleep 8
docker compose exec app php artisan test tests/Feature/Security
