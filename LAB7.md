# Lab 7 — Secure a Vulnerable Laravel REST API (branch `lab-7`)

Builds on the Lab 6 reference solution (SSRF fetcher, webhooks and the database cache are already in place).

## Instructor-provided starting state (Part A)

The lab says "if the instructor has not supplied a pre-populated database, generate the model and migration".
Here the instructor has: **do not run `make:model Order -mf`** (it would create a duplicate model and table).

| Lab text | In this app |
|---|---|
| `orders` table, `Order` model (A2/A3) | `database/migrations/2026_01_01_000010_create_orders_table.php`, `app/Models/Order.php` (deliberately over-permissive `$fillable`), `database/factories/OrderFactory.php` |
| `VulnerableOrderController` + routes (A4/A5) | `app/Http/Controllers/Api/VulnerableOrderController.php`; routes `GET/PATCH /api/lab-vulnerable/orders/{id}` and `POST /api/lab-vulnerable/orders/{id}/process` in `routes/api.php`, behind `auth:sanctum` |
| `php artisan ...` | `docker compose exec app php artisan ...` |
| `php artisan serve --host=127.0.0.1 --port=8000` | already running; use `base_url = http://localhost:8000` |
| Alice and Bob | `alice@example.test` (Alpha) and `bob@example.test` (Beta), password `Lab-Passw0rd!` — **user ids 5 and 6** |
| Training tokens (A6) | seeded, fixed fictional tokens (valid 30 days, reseeded on every start): `alice_token = lab7-alice-training-token-0001`, `bob_token = lab7-bob-training-token-0002` — send as `Authorization: Bearer <token>` |
| Orders | ORD-001 (id 1, Alice), ORD-002 (id 2, Bob), ORD-003 (id 3, Alice), ORD-004 (id 4, Bob), ORD-005 (id 5, Alice). Use ids 3 or 5 for the duplicate-processing demo so order 1 stays a draft. In B3 use Bob's user id **6** for `user_id`. |

Reset the data at any time: `docker compose exec app php artisan migrate:fresh --seed --force`
(the container also does this on every start; `--force` is required once `APP_ENV=production`).
Postman and curl need no CSRF token or cookie for these routes — they use bearer tokens.

## Where participants edit

* `app/Policies`, `app/Http/Requests`, `app/Http/Controllers/Api`, `app/Http/Resources`, `app/Services`, `app/Providers/AppServiceProvider.php`, `app/Models`: mounted, edit on the host.
* `routes/api.php`, `bootstrap/app.php`, `tests/`: mounted.
* `database/migrations`: mounted. `make:model OrderProcessing -m` (F2) creates the migration there; run `docker compose exec app php artisan migrate`. The container re-runs all migrations on every start.
* `docker/env.lab` (Part G1: `APP_ENV=production`, `APP_DEBUG=false`), then `docker compose restart app`.
* `docker compose exec app php artisan make:policy OrderPolicy --model=Order` etc.

## Notes

* **Triggering a 500 for G5**: while the vulnerable routes exist, `GET /api/lab-vulnerable/orders/abc` (a non-numeric id) raises a `TypeError`. With `APP_DEBUG=true` the JSON contains the stack trace; with `APP_DEBUG=false` it is the generic `Server Error`. Remove the `lab-vulnerable` routes after Part C/H (the reference solution does) — leaving them in is itself a finding.
* **Security log (G3)**: `LOG_CHANNEL=single`, so entries are in `storage/logs/laravel.log` (`docker compose exec app tail -n 20 storage/logs/laravel.log`).
* **APP_ENV=production** also affects other artisan commands you run by hand (they ask for confirmation or need `--force`). Tests are unaffected (`APP_ENV=testing`).
* **SQLite and `lockForUpdate()`**: SQLite ignores `FOR UPDATE` (it serializes writers instead), so the `unique(order_id)` / `unique(operation_key)` constraints are the guard you can actually observe here. Row locking only shows its effect on MySQL/PostgreSQL.
* **Rate limit state** is in the database cache (`CACHE_STORE=database` from Lab 6) and is cleared when the container restarts.
* Pest tests that create orders use `Order::forceCreate(...)` because `$fillable` is restricted in Part C3; the seeder does the same.

## Defects in the lab text that the reference solution fixes

1. **Authorization denials are never logged by the lab's G3 code**: `AuthorizationException` is on Laravel's default "do not report" list, so the `report()` callback never runs. The reference adds `$exceptions->stopIgnoring(AuthorizationException::class)`.
2. **The 404 for a missing order leaks the model class** (`No query results for model [App\Models\Order] 99`) even with debug off; the reference returns a generic JSON message through a `render()` callback.
3. **Reusing an operation key** on a second order would hit the unique index and return a 500; the service catches `UniqueConstraintViolationException` and answers 422 (this also covers the real concurrent-request race).
4. The rate limiters fall back to the client IP if no user is resolved.
5. The reference removes the `/api/lab-vulnerable/*` routes (the controller file stays, unrouted).

**Status: unverified** — written without being run (no PHP/Docker available while building).

## Instructor materials (remove before distributing: `solutions/`, `scripts/apply-*.sh`, `INSTRUCTOR_*`)

* `solutions/lab7/` — model, policy, form request, resource, controllers, processing service and ledger migration, `SecurityEventLogger`, `AppServiceProvider`, `routes/api.php`, `bootstrap/app.php` and three Pest files (about 45 test cases, including the lab's 11).
* `sh scripts/apply-lab7-fixes.sh` — copies the solution, sets `APP_ENV=production` / `APP_DEBUG=false`, restarts the app and runs the tests.
