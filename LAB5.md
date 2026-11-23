# Lab 5 — Secure File Upload Pipeline and CSP (branch `lab-5`)

Builds on Lab 4 (your completed Lab 4 work is the base). Accounts: see `README.md` (password `Lab-Passw0rd!`).

## Name collision: the old document feature was renamed

Labs 1–2 already had a *project* document feature (`Document`, `documents` table, `DocumentController`,
`DocumentPolicy`, `StoreDocumentRequest`, route names `documents.*`, URL `GET /documents/{id}/download`).
Lab 5 uses all of those names for the new user-owned pipeline, so on this branch the old feature is
**`ProjectDocument`**: `project_documents` table, `ProjectDocumentController`, `ProjectDocumentPolicy`,
`StoreProjectDocumentRequest`, routes `project-documents.store` / `project-documents.download`
(`GET /project-documents/{id}/download`). The nested route `projects.documents.download` is unchanged.
Behaviour is identical; the Lab 1/2 tests were updated to the new names.
The reference solutions in `solutions/` for Labs 1–3 (and their `scripts/apply-*.sh`) still use the old names — they are not valid on this branch.

| Lab 5 text | In this app |
|---|---|
| `InsecureUploadController` (Part A) | `app/Http/Controllers/InsecureUploadController.php` — inspect only, **not routed** |
| `php artisan make:request StoreDocumentRequest`, `make:model Document -m`, `make:controller DocumentController`, `make:policy DocumentPolicy`, `make:command …`, `make:middleware SecurityHeaders` | run inside the container: `docker compose exec app php artisan make:…` (names are free now) |
| `php.ini` limits (B3) | edit **`docker/php/lab5.ini`** on the host, then `docker compose restart app` (starts at 20M/25M/20) |
| `config/filesystems.php` (C1) | in the repo and bind-mounted — add the `secure_documents` disk |
| `bootstrap/app.php` (F3), `routes/web.php` | edit on the host |
| GD for `UploadedFile::fake()->image()` | installed in the Dockerfile (**rebuild required**) |

## CSP compatibility work already done

The lab's policy (`script-src 'self' 'nonce-…'`, `style-src 'self'`) blocks inline styles and handlers, so the
existing pages were changed to work under it: the layout's `<style>` block moved to `public/css/app.css`, inline
`style=""` attributes became classes, and the delete confirm (`onsubmit`) now uses `data-confirm` handled by
`public/js/app.js`. Files in `public/css` and `public/js` are bind-mounted.

## Starting state

Nothing of the secure pipeline exists yet: no `documents` table/model, no `POST /documents`, no `secure_documents` disk,
no scanner, no policy, no `SecurityHeaders` middleware and no `/security-demo` route. Participants build Parts B–F.

## Run

```bash
docker compose up -d --build      # rebuild: GD extension, new config mounts, public assets
docker compose exec app php artisan route:list
docker compose exec app php artisan test tests/Feature/Security
```

## Notes

* Scan command: `docker compose exec app php artisan documents:scan`. Upload via Postman/browser needs session + CSRF (see Lab 4 notes); a login cookie jar with curl works too.
* **Infected fixture** (harmless): a file named `infected.pdf` containing `%PDF-1.4`, a line `TRAINING_SIMULATED_THREAT`, then `%%EOF`.
* Private storage lives in the container at `storage/app/secure-documents`. `migrate:fresh` on restart clears the database but not these files.
* `SESSION_SECURE_COOKIE=true` (Lab 4) is still on; see `LAB4.md` for the Safari/HTTP caveat. HSTS is only sent over HTTPS.
* Scanner hardening vs the lab text: `readStream()` returns `null` (not `false`) when the disk does not throw, so the reference scanner checks `is_resource()` and fails closed.

## Instructor materials (remove before distributing: `solutions/`, `scripts/apply-*.sh`, `INSTRUCTOR_*`)

* `solutions/lab5/` — Form Request, signature validator, migration, model, controller, policy, scanner, scan command,
  `SecurityHeaders`, demo view, `filesystems.php`, hardened `lab5.ini`, routes, `bootstrap/app.php`, and two Pest files in `tests/`.
* `sh scripts/apply-lab5-fixes.sh` — copies the solution (overwriting `routes/web.php`, `bootstrap/app.php`, `config/filesystems.php`,
  `docker/php/lab5.ini`), restarts the app and runs the upload and CSP tests.
