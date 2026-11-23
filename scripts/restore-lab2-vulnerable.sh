#!/bin/sh
# Reset the Lab 2 data and remove the reference test. To also revert code you changed,
# commit the lab-2 starting state first, then use: git checkout -- app/
set -e
rm -f tests/Feature/ProjectAuthorizationTest.php
docker compose exec app php artisan migrate:fresh --seed --force
