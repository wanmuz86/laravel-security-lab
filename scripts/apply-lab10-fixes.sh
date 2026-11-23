#!/bin/sh
# Instructor/after-lab helper for Lab 10: copies the reference solution from ./solutions/lab10 (Policy, controller,
# Form Request, throttle limiters, routes and the regression tests) and runs the document tests.
# Run from the project root on the HOST. No container restart is needed (app/, routes/ and tests/ are bind-mounted).
set -e

S=solutions/lab10

mkdir -p tests/Feature/Security

cp $S/DocumentController.php     app/Http/Controllers/Api/DocumentController.php
cp $S/DocumentPolicy.php         app/Policies/DocumentPolicy.php
cp $S/UpdateDocumentRequest.php  app/Http/Requests/UpdateDocumentRequest.php
cp $S/AppServiceProvider.php     app/Providers/AppServiceProvider.php
cp $S/api.php                    routes/api.php
cp $S/tests/*.php                tests/Feature/Security/

docker compose exec -T app php artisan test tests/Feature/Security/DocumentAuthorizationTest.php tests/Feature/Security/DocumentApiSecurityTest.php tests/Feature/Security/DocumentApiBaselineTest.php
