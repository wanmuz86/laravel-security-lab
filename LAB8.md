# Lab 8 — Secure the Laravel Software Supply Chain (branch `lab-8`)

Builds on the Lab 7 reference solution (secured order API) and a green test suite (**209 tests** after the reference
solution; **202** in the starter).

## Instructor-provided starting state

| Lab text | In this app |
|---|---|
| "Working Laravel app with `composer.json` and `composer.lock` committed" | [composer.json](composer.json) and [composer.lock](composer.lock) are in the repository root and **mounted into the container**. `vendor/` and the Laravel skeleton stay inside the Docker image. |
| `composer ...`, `php artisan ...` | `docker compose exec app composer ...` / `docker compose exec app php artisan ...` (use the normal terminal so Composer can prompt) |
| `git ...`, `gitleaks ...` | Run these **on the host**, in the repository root. The container has no `.git`. Install Gitleaks on the host: `brew install gitleaks` (macOS), `scoop install gitleaks` / `choco install gitleaks` (Windows/Laragon). |
| Output files (`composer-audit.json`, `sbom.json`, Gitleaks reports) | Write them to **`reports/`** (mounted, so files created in the container appear on the host). Example: `composer audit --format=json > reports/composer-audit.json`. |
| `.github/dependabot.yml`, `.github/workflows/...` | Create them on the host under `.github/` (the folder exists and is mounted so tests can see it). |
| "At least one intentionally outdated dependency and a vulnerability fixture" | Two fixture packages are pinned to old, genuinely vulnerable releases (see below). The advisories are **live Packagist data**; a saved copy is in [docs/lab8-audit-baseline.json](docs/lab8-audit-baseline.json) (captured 2026-11-22) for offline use or if counts drift. |
| "Fictional secret-scanning fixture" | A fictional payment gateway key was committed and later removed (history only), plus the real Lab 6 training webhook secret in `docker/env.lab`. |
| Remediation branch (C1) | `git switch -c security/dependency-remediation` on the host, as written. |

After the container is **recreated** (`docker compose up -d --force-recreate`) or restarted, the entrypoint runs
`composer install`, so `vendor/` always matches the mounted `composer.lock` (needs network the first time).
If you change `docker-compose.yml`, recreate the container; a plain `restart` does not remount.

## The fixtures

| Package | Locked | Constraint | Advisories | Notes |
|---|---|---|---|---|
| `guzzlehttp/guzzle` | 7.8.2 | `~7.8.2` | 9 (1 high, 8 medium) | **Reachable**: pulled in by `laravel/framework` and used through the HTTP client. The `~7.8.2` pin blocks the fix, so the remediation is a constraint change. |
| `dompdf/dompdf` | 2.0.3 | `>=2.0.3 <2.0.4` | 7 (5 medium, 2 low) | Plus 4 transitive packages. **Not referenced** by any application code. Advisories affect everything below 3.1.6, so there is no clean 2.x release. |

Baseline numbers: **16 advisories, 2 packages**, `composer audit` exits **1**, `composer outdated --direct` lists **7**
packages, `composer validate --strict` is valid. `composer audit --no-dev` reports the same 16, so both are production
dependencies.

## Notes per part

* **A5** `composer why guzzlehttp/guzzle` shows the root requirement (`~7.8.2`) and `laravel/framework`. `composer why dompdf/dompdf` shows only the root requirement.
* **B5 reachability**: `grep -rniE "dompdf" app routes resources` returns nothing (not reachable). Guzzle is reachable through Laravel's HTTP client (the only direct use in this app is the unrouted `InsecureFetchController`; discuss that exposure honestly).
* **C3** `composer update guzzlehttp/guzzle --with-all-dependencies --dry-run` does **not** simply offer the new version: the `~7.8.2` constraint blocks it, and Composer 2.11 also says newer versions were "not loaded because they are affected by security advisories". Use `composer why-not guzzlehttp/guzzle 7.16.5` to see the root pin, then fix the constraint (C4's second form).
* **C4** `composer require guzzlehttp/guzzle:^7.8.2` upgrades to the current 7.x. For dompdf, nothing in 2.x is fixed and the app does not use it, so `composer remove dompdf/dompdf` (this is the "Not Applicable → removed" decision).
* **C6** `php artisan test` should stay green (209 passing with the reference solution).
* **D2** In an interactive terminal Composer asks whether to allow the CycloneDX plugin; answer after checking the publisher. Without a terminal it refuses until you run `composer config --no-plugins allow-plugins.cyclonedx/cyclonedx-php-composer true`.
* **D3** `composer cyclonedx:make-sbom --output-format=JSON --output-file=reports/sbom.json`. Real output differs from the lab's sample: components have a separate `group` (`laravel`) and `name` (`framework`), and versions keep the `v` prefix (`v12.73.0`), exactly as in `composer.lock`. The reference SBOM is CycloneDX 1.5 with 135 components.
* **E** The lab's workflow runs `composer install` and `php artisan test`, which cannot work here (no skeleton in the repo). The reference workflow in `solutions/lab8/github/workflows/` audits the lock file directly (`composer audit --locked`) and runs the tests in Docker. **It has not been run on GitHub.**
* **F** `gitleaks git . --redact ...` and `gitleaks dir . --redact ...` (v8.30.1 syntax as in the lab). Expected baseline: **2 findings in history, 1 in the working tree**:

  | ID | File | Rule | Where | Verdict |
  |---|---|---|---|---|
  | LEAK-01 | `config/services.php` | `generic-api-key` | history only, commit `5f414a4` (removed in `19a865a`) | Confirmed (fictional): rotate, move to env (already done), history cleanup decision |
  | LEAK-02 | `docker/env.lab` line 27 (25 in the working tree) | `generic-api-key` | commit `6459690` and working tree | Training-only webhook secret; accept with justification |

  The reference closes both with a documented `.gitleaksignore` (`solutions/lab8/gitleaksignore`), because rewriting shared history would break every clone. For the "purge history" step use a **throwaway clone** with `git filter-repo` (not run as part of building this lab).
* **G** Reference numbers: advisories 16 → 0, outdated direct 7 → 6 (only major-version jumps remain), tests green, unaccepted secret findings 2 in history (the same LEAK-02 secret also shows in the working tree) → 0, SBOM generated. See `solutions/lab8/supply-chain-assessment.md`.

## Defects in the lab text that the reference handles

1. **CI workflow cannot run as written**: this repository holds no Laravel skeleton, so `composer install` + `php artisan test` fail on a fresh checkout. Replaced by `composer audit --locked` plus Docker-based tests.
2. **Sample SBOM shape** (`"name": "laravel/framework"`) differs from the real CycloneDX output (separate `group` and `name`).
3. **Plugin approval** (D2) needs an explicit `allow-plugins` decision outside an interactive terminal.
4. **Dry-run (C3) hides the fix** behind the pinned constraint and the advisory-blocking policy; participants must read the message and change the constraint.
5. **`.gitignore`, `.env.example`** do not exist in the starter on purpose (F5); the reference adds them. The lab says `.env.example` contains `STRIPE_SECRET_KEY=`; the reference keeps those placeholders empty.

## Where participants edit

* `composer.json`, `composer.lock`: mounted, commit them (C7).
* `.github/`, `.gitignore`, `.env.example`, `.gitleaksignore`: host files in the repo root.
* `reports/`: audit, SBOM and Gitleaks output (the `.gitkeep` is the only tracked file).
* `config/services.php`: mounted; already reads the key from the environment.

## Instructor materials (remove `solutions/`, `scripts/apply-*.sh` and `INSTRUCTOR_*` before distributing; do not squash history, it would destroy the leak fixture)

* `solutions/lab8/` — remediated `composer.json`/`composer.lock` (guzzle `^7.8.2`, dompdf removed, CycloneDX dev dependency), Dependabot config, workflow, `.gitignore`, `.env.example`, `gitleaksignore`, `SupplyChainTest.php`, and a sample assessment report.
* `sh scripts/apply-lab8-fixes.sh` — copies the solution, re-syncs `vendor/`, validates, audits, generates `reports/*`, runs Gitleaks (if installed) and the tests.
* **History fixture**: the leak lives in commits `5f414a4` ("Add payment gateway integration config") and `19a865a` ("Load payment gateway credentials from the environment"). The `.gitleaksignore` fingerprints contain those SHAs, so **do not amend, rebase or squash them**; if you do, regenerate the fingerprints with `gitleaks git . --report-format json --report-path /tmp/r.json`.
* Pest guard tests (`SupplyChainTest.php`) are offline: they check the lock file, `config/services.php`, and the SBOM/Dependabot/workflow files when present.

## Changes to earlier labs made for this baseline

Lab 8 requires a passing baseline, so five failing tests from Labs 1–5 were fixed (commit `e3f6074`): three were test bugs (garbage-collected fake image, `actingAs` leaking into the Sanctum token check, 403-vs-404 on the signed storage route), one expected 403 where tenant scoping returns 404, and one was a real hole, the Lab 1 "bonus" finding B01 (`TeamController::updateRole` crossing organizations), now fixed with the reference one-line tenant check.

**Status: verified (2026-11-22)** in the Docker container: starter state, the full remediation path (C3–C6), the SBOM (D2–D5), Gitleaks on history and working tree with and without the ignore list, and the full test suite. **Not run:** the GitHub Actions workflow and Dependabot on GitHub, and `git filter-repo`.
