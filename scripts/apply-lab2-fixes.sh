#!/bin/sh
# Instructor/after-lab helper for Lab 2: copies the reference fixes from ./solutions/lab2,
# installs the reference Pest test and runs it. Run from the project root on the HOST.
set -e

cp solutions/lab2/ProjectController.php  app/Http/Controllers/ProjectController.php
cp solutions/lab2/DocumentController.php app/Http/Controllers/DocumentController.php
cp solutions/lab2/ProjectPolicy.php      app/Policies/ProjectPolicy.php
cp solutions/lab2/DocumentPolicy.php     app/Policies/DocumentPolicy.php
cp solutions/StoreDocumentRequest.php    app/Http/Requests/StoreDocumentRequest.php   # used by DocumentController@store
cp solutions/lab2/ProjectAuthorizationTest.php tests/Feature/ProjectAuthorizationTest.php

docker compose exec app php artisan test --filter=ProjectAuthorization
