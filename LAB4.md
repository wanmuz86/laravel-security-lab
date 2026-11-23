# Lab 4 — Authentication and Session Hardening (branch `lab-4`)

Builds on Lab 3: this branch starts from the **Lab 3 reference fixes**, then adds the Lab 4 environment
and an intentionally insecure authentication setup. Accounts: see `README.md` (password `Lab-Passw0rd!`).

## What this branch adds

| Lab 4 requirement | In this app |
|---|---|
| Laravel Sanctum installed | `Dockerfile` (`composer require laravel/sanctum`), `HasApiTokens` on `User`, migration `…000007_create_personal_access_tokens_table` |
| Database sessions + `sessions.user_id` | `SESSION_DRIVER=database`, migration `…000006_create_auth_support_tables` (also `password_reset_tokens`) |
| Mail: log mailer | `MAIL_MAILER=log` → reset mails appear in `storage/logs/laravel.log` (inside the container) |
| Pest | already installed (Lab 2) |
| `config/session.php`, `config/auth.php`, `bootstrap/app.php`, `phpunit.xml` | now in the repo and bind-mounted — edit them on the host |
| `.env` | edit **`docker/env.lab`** on the host, then `docker compose restart app` |
| `/dashboard` | redirects to the project list (the lab's post-login URL) |

## Insecure starting state (what participants inspect — Part A)

* `docker/env.lab`: `SESSION_LIFETIME=120`, `SESSION_ENCRYPT=false`, `SESSION_SECURE_COOKIE=false`, `SESSION_SAME_SITE=lax`.
* `AuthController@login`: **no** `session()->regenerate()`; `@logout`: **no** `session()->invalidate()` / `regenerateToken()`; no rate limiting.
* `config/auth.php`: reset token expiry 60 minutes (lab policy: 30).
* No password-reset POST handlers, no token endpoints, no `abilities` middleware aliases, no `/api/secure-products` routes — participants build these (Parts C–E).
  `GET /forgot-password` and `GET /reset-password/{token}` exist only so the reset notification can build its URL.

## Run

```bash
docker compose up -d --build        # rebuild: Sanctum, new migrations, config mounts
docker compose exec app php artisan route:list
docker compose exec app php artisan test tests/Feature/Security   # after adding the tests (Part F)
```

Reset mails: `docker compose exec app tail -n 50 storage/logs/laravel.log`.

## Notes and limitations

* **Secure cookie:** `SESSION_SECURE_COOKIE=true` needs HTTPS. Chrome, Edge and Firefox still accept it on `http://localhost`;
  Safari does not (login will appear to loop). Use an HTTPS proxy for realistic verification (lab Step B2, F8).
* **Testing sessions/CSRF:** Pest tests run with CSRF verification off. The CSRF-protected token endpoints are exercised
  by `scripts/lab4-api-demo.sh` (curl with cookie jar + CSRF token) against a real server.
* **Database:** SQLite (the lab says MySQL or SQLite — both are allowed). `migrate:fresh` runs on each container start, so
  restarting the app logs everyone out and drops tokens.
* The reset forms in the browser post to JSON endpoints (as in the lab), so the browser shows JSON after submitting.
* Test-env caveat: `phpunit.xml` uses `SESSION_DRIVER=array`; the lab's Step F2 value (`database`) is optional here.

## Instructor materials (remove before distributing: `solutions/`, `scripts/apply-*.sh`, `INSTRUCTOR_*`)

* `solutions/lab4/` — `LoginController`, `LoginRequest`, `PasswordResetController`, `Api/TokenController`,
  `Api/ProjectApiController` (SEC-14), routes, `bootstrap/app.php`, `config/auth.php`, `env.lab.hardened`, and 3 Pest files in `tests/`.
* `sh scripts/apply-lab4-fixes.sh` — copies the solution (overwriting `routes/`, `bootstrap/app.php`, `docker/env.lab`),
  restarts the app, runs `tests/Feature/Security` (should be all green).
* `sh scripts/lab4-api-demo.sh read|manage` — session login → token → API calls from the host.
* Return to the insecure state with git: `git checkout -- .` (commit your own work first), then `rm -r tests/Feature/Security`.
