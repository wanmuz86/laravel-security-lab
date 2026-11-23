# Lab 2 — Exploit and Fix Multi-Tenant IDOR (branch `lab-2`)

Same application as Lab 1. The lab text uses illustrative names/IDs; use this mapping.

| Lab text | In this app |
|---|---|
| Alice (Admin, Org A) | `admin.alpha@example.test` |
| Adam (Member, Org A) | `staff.alpha@example.test` |
| Bob (Admin, Org B) | `admin.beta@example.test` |
| Bella (Member, Org B) | `staff.beta@example.test` |
| Projects 101 / 102 (Org A) | 1 / 2 (3 also Org A) |
| Projects 202 / 203 (Org B) | 4 / 5 (6 also Org B) |
| Document 55 (Org A) / 88 (Org B) | 1 (Alpha, project 1) / 4 (Beta, project 4) |

Password for all accounts: `Lab-Passw0rd!`

## Starting state (intentionally vulnerable)

* `GET /projects/4` as Alpha → 200 with Beta data (no tenant check).
* `PUT /projects/4` as `admin.alpha` → succeeds (`ProjectPolicy@update` has no organization check).
* `GET /projects/1/documents/4` as Alpha → returns Beta's document (nested IDOR, Activity 5):
  the parent project is checked, the document is fetched globally.
* `GET /documents/4/download` as Alpha → returns Beta's document (flat URL).
* `DELETE /projects/4` as `admin.alpha` → 403 (already protected by the policy, but via 403 not 404).
* Lab 1 flaws (SQL injection in search, verbose errors, upload validation) are still present and out of scope.

## Running tests

```bash
docker compose up -d --build        # rebuild once: Pest, factories and the nested route are new
docker compose exec app php artisan test --filter=ProjectAuthorization
# or: docker compose exec app ./vendor/bin/pest tests/Feature/ProjectAuthorizationTest.php
```

Participants write `tests/Feature/ProjectAuthorizationTest.php` (Activity 6).
Factories: `Organization`, `User` (`->admin()` / `->member()`), `Project`, `Document`.

## Instructor materials

* `solutions/lab2/` — reference controllers, policies and the reference Pest test.
* `scripts/apply-lab2-fixes.sh` — applies them and runs the tests (should be all green).
* Remove `solutions/`, `scripts/apply-*.sh`, and `INSTRUCTOR_*` before distributing.
