# Lab 3 — SQL Injection, XSS and CSRF Hardening (branch `lab-3`)

Builds on Lab 2: this branch starts from the **Lab 2 reference fixes** (tenant-scoped queries + Policies),
then re-introduces the Lab 3 weaknesses. Accounts: see `README.md` (password `Lab-Passw0rd!`).

| Lab text | In this app |
|---|---|
| `ProjectSearchController` | `app/Http/Controllers/ProjectSearchController.php` (`GET /projects/search?q=`, route `projects.search`) |
| Comment feature | `POST /projects/{project}/comments` → `CommentController@store`; comments shown on the project page |
| Project edit form | `resources/views/projects/edit.blade.php` |
| Database | **SQLite** (the lab text says MySQL; `LIKE` wildcard escaping and case sensitivity differ) |

## Seeded weaknesses (starting state)

| Component | Weakness |
|---|---|
| `ProjectSearchController` | `DB::select("... LIKE '%{$keyword}%'")` string concatenation; raw exception details returned on error. `' OR 1=1 --` bypasses the tenant filter and lists other organizations' projects |
| `resources/views/projects/show.blade.php` | `{!! $comment->content !!}` — stored XSS |
| `resources/views/projects/search.blade.php` | `{!! request('q') !!}` — reflected XSS |
| `CommentController@store` | no Form Request: no required/max rules (empty comment → DB error), no authorization (any user can comment on any project ID) |
| `resources/views/projects/edit.blade.php` | `@csrf` missing — submitting the form fails with HTTP 419 until participants restore it. (Fails closed; it is not exploitable. The CSRF middleware itself is intact.) |

Seeded: project 1 has the comment "Project meeting scheduled for Monday."

## Run

```bash
docker compose up -d --build     # rebuild/restart once: new migration (comments table) + seed data
cp solutions/lab3/SecurityHardeningTest.php tests/Feature/   # optional: see the tests fail first
docker compose exec app php artisan test --filter=SecurityHardening
```

Participants write `tests/Feature/SecurityHardeningTest.php` (Activity 6). Factories: `Comment` added.

## Instructor materials

* `solutions/lab3/` — reference controller, Form Request, views, Pest test.
* `sh scripts/apply-lab3-fixes.sh` — applies them and runs the tests (should be all green).
* `scripts/restore-vulnerable.sh` resets the data. Remove `solutions/`, `scripts/apply-*.sh` and `INSTRUCTOR_*` before distributing.
