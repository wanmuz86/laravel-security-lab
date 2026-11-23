#!/bin/sh
# Instructor/after-lab helper for Lab 3: copies the reference fixes from ./solutions/lab3,
# installs the reference Pest test and runs it. Run from the project root on the HOST.
set -e

cp solutions/lab3/ProjectSearchController.php app/Http/Controllers/ProjectSearchController.php
cp solutions/lab3/CommentController.php       app/Http/Controllers/CommentController.php
cp solutions/lab3/StoreProjectCommentRequest.php app/Http/Requests/StoreProjectCommentRequest.php
cp solutions/lab3/search.blade.php resources/views/projects/search.blade.php
cp solutions/lab3/show.blade.php   resources/views/projects/show.blade.php
cp solutions/lab3/edit.blade.php   resources/views/projects/edit.blade.php
cp solutions/lab3/SecurityHardeningTest.php tests/Feature/SecurityHardeningTest.php

docker compose exec app php artisan test --filter=SecurityHardening
