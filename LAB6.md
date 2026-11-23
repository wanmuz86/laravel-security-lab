# Lab 6 — Anti-SSRF Fetcher and Secure Webhook Verification (branch `lab-6`)

Builds on the Lab 5 reference solution (secure upload pipeline + CSP are already in place on this branch).
Accounts: see `README.md` (password `Lab-Passw0rd!`).

## Mapping the lab text to this Docker setup

| Lab text | In this app |
|---|---|
| `php artisan ...` | `docker compose exec app php artisan ...` (artisan lives in the container) |
| `php artisan serve --host=127.0.0.1 --port=8000` | already running in the container, published as <http://localhost:8000> |
| A3: `php -S 127.0.0.1:9001` in `ssrf-test-service/` | the files are in `./ssrf-test-service/` (mounted). Start the service **inside the container**, because the app's "127.0.0.1" is the container: `docker compose exec -d app php -S 127.0.0.1:9001 -t ssrf-test-service`. Restarting the app stops it; run the command again. From your Mac you can no longer open it in a browser (it listens only inside the container) — check it with `docker compose exec app curl -s http://127.0.0.1:9001/internal.php`. |
| A2/A4 `InsecureFetchController` | `app/Http/Controllers/InsecureFetchController.php` — **not routed**. To demonstrate (A4) temporarily add, in `routes/web.php` inside the `auth` group, `Route::get('/insecure-fetch', [\App\Http\Controllers\InsecureFetchController::class, 'fetch']);`, call `/insecure-fetch?url=http://127.0.0.1:9001/internal.php` while logged in, then remove the route. `http://127.0.0.1:9001/redirect.php` answers with a 302 to `internal.php`, which shows redirect-following. |
| B2 `config/integrations.php` | a stub exists (`return [];`); replace its contents (bind-mounted, edit on the host) |
| B2 `.env` values | `.env` is rebuilt from **`docker/env.lab`** on every start, so add `FETCH_ALLOWED_HOSTS=...` and `WEBHOOK_SHARED_SECRET=...` there, then `docker compose restart app`. Generate a secret with `docker compose exec app php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`. |
| `make:request`, `make:controller`, `make:middleware` | `docker compose exec app php artisan make:...` |
| D4/E4 `bootstrap/app.php`, `routes/*.php` | edit on the host; keep the existing aliases and routes (this branch already has `org.admin`, `abilities`, `ability`, the CSP middleware and the Lab 4 API routes) |
| D7 `sign-webhook.php` | `ssrf-test-service/sign-webhook.php`. Run it in the container: `docker compose exec app php ssrf-test-service/sign-webhook.php` (optionally `evt_id` and a timestamp offset such as `-600`). It prints the headers and a ready-to-run `curl` command. |
| E2 cache table | `docker compose exec app php artisan make:cache-table` (creates a migration in `database/migrations`, which is mounted), then `docker compose exec app php artisan migrate`. The database is rebuilt on each start; the migration file stays. Set `CACHE_STORE=database` in `docker/env.lab`. |
| F `phpunit.xml` cache isolation | already `CACHE_STORE=array` |
| F tests | run in the container: `docker compose exec app php artisan test tests/Unit/SafeUrlValidatorTest.php` etc. |

## Changes on this branch

* `docker-compose.yml`: mounts `config/integrations.php` and `ssrf-test-service/` (no image rebuild is needed — run `docker compose up -d` once to pick up the new mounts).
* `tests/Pest.php`: unit tests now use the Laravel `TestCase` (the lab's `SafeUrlValidatorTest` calls `config()`, which fails without it).
* The Laravel HTTP client / cURL are already in the image.

## Notes for running the exercises

* **Approved host.** The lab needs an instructor-approved HTTPS host. The reference setup uses `example.com` (set `FETCH_ALLOWED_HOSTS` in `docker/env.lab`); the container needs working DNS and outbound HTTPS. The lab's placeholder `approved.example.com` does not exist in DNS.
* **Local services can never pass the secure fetcher** (HTTPS only, public IPs only). The "approved host redirects to loopback" scenario therefore needs an instructor-controlled public HTTPS endpoint that answers 302; without one, demonstrate the redirect rule with the insecure controller (`redirect.php`) and the code review of `CURLOPT_FOLLOWLOCATION => false`.
* **Calling `POST /integrations/fetch`** needs the session and a CSRF token (same approach as Lab 5): log in, read `_token` from a page such as `/projects/create`, and send JSON `{"url":"...","_token":"..."}` with `Accept: application/json` and the session cookie. Webhooks (`POST /api/webhooks/training`) need neither.
* Lab text defects fixed in the reference solution: the validator skips CNAME entries returned by `dns_get_record` (otherwise any aliased host is rejected), IPv4-mapped IPv6 addresses are unwrapped, extra special-purpose ranges are blocked, and the DNS resolver can be injected so tests do not need real DNS.
* The lab's cache-based replay check reserves the event id *before* the payload is validated, so an event with an invalid payload still uses up its id (a limitation the lab text calls out).
* **Status: unverified** — the code was written without being run.

## Starting state

No `SafeUrlValidator`, `SecureHttpFetcher`, controllers, middleware, routes or tests for Lab 6 exist yet; `config/integrations.php` is an empty stub.

## Instructor materials (remove before distributing: `solutions/`, `scripts/apply-*.sh`, `INSTRUCTOR_*`)

* `solutions/lab6/` — validator, fetcher, form request, controllers, both middleware, `integrations.php`, cache migration, routes, `bootstrap/app.php` and three Pest files (about 60 test cases).
* `sh scripts/apply-lab6-fixes.sh` — copies the solution, adds `FETCH_ALLOWED_HOSTS`, a random `WEBHOOK_SHARED_SECRET` and `CACHE_STORE=database` to `docker/env.lab`, runs `docker compose up -d` and the tests.
