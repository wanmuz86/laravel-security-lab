# Docker-free setup (Labs 1-4)

Use this if Docker is too slow on your laptop. Needs PHP 8.2+ (extensions: pdo_sqlite, mbstring, zip) and Composer 2.

```bash
./scripts/setup-local.sh     # one-off: installs Laravel 12 + Pest + Sanctum, builds DB (~2-4 min)
php artisan serve            # http://localhost:8000
```

Wherever the lab says `docker compose exec app php artisan ...`, just run `php artisan ...` in this folder.
Reset data: `php artisan migrate:fresh --seed --force`. Run tests: `php artisan test`.
The `.env` is rebuilt from `docker/env.lab` each time you re-run the setup script.

## Windows (Git Bash)
Run the script from **Git Bash** (not PowerShell/cmd). `php` and `composer` must be on your PATH
(check with `php -v` and `composer -V`), and `extension=pdo_sqlite`, `mbstring` and `zip` must be enabled in `php.ini`.
