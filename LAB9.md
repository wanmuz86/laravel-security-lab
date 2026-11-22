# Lab 9 — Build an Automated Laravel Security Pipeline (branch `lab-9`)

Builds on the Lab 8 reference solution (remediated dependencies, Dependabot, Gitleaks ignore list, SBOM tooling).
Baseline: **212 passing tests**, Composer audit clean, Gitleaks clean, PHPStan clean against a baseline.

## Instructor-provided starting state

| Lab text | In this app |
|---|---|
| `php artisan ...`, `composer ...`, `vendor/bin/phpstan ...` | `docker compose exec app <command>`. The Laravel skeleton and `vendor/` live in the Docker image, not the repository. |
| `git`, `gitleaks`, `trivy`, ZAP, Docker | On the host. Trivy and ZAP are run from their Docker images (`aquasec/trivy:0.76.2`, `ghcr.io/zaproxy/zaproxy:2.17.0`); Gitleaks as a host binary (`brew install gitleaks`). |
| Starter `.github/workflows/security.yml` (A2) | Provided, but adapted: it builds and starts the app in Docker and runs the tests there (the lab's `composer install` + `php artisan test` cannot work without a skeleton in the repo). |
| Pest security tests (B1) | `tests/Feature/Security/` (auth, upload, webhook, SSRF, order API, error handling, supply chain, **production configuration**). |
| PHPStan (B3/B4) | Installed (`phpstan/phpstan` + `larastan/larastan`), configured in `phpstan.neon`, level 5 on `app/`. **11 pre-existing findings are recorded in `phpstan-baseline.neon`**, so the gate is "no unbaselined errors". |
| Harmless secret fixture (C5) | `scripts/seed-lab9-failure.sh gitleaks` (see below). The history leaks from Lab 8 are accepted in `.gitleaksignore`. |
| ZAP configuration (E5) | `zap/baseline.conf`. FAIL: `10020` (anti-clickjacking), `10038` (CSP not set). Everything else is WARN. |
| Production settings (F3) | `tests/Fixtures/production.env.example` (non-secret) checked by `ProductionConfigurationTest.php`, which also asserts the live security headers. |
| Branch / PR | `git switch -c lab9/security-pipeline`; open a PR into `main`. |

After changing `docker-compose.yml`, `bootstrap/app.php` or any other **single-file mount**, recreate the container
(`docker compose up -d --force-recreate`). A plain restart, or an editor that replaces the file, leaves the container
looking at the old file.

## Baseline results (what a clean run looks like)

| Tool | Result |
|---|---|
| Pest security tests | pass (127 + 1 skipped in `tests/Feature/Security`) |
| PHPStan | `[OK] No errors` (11 baselined) |
| Composer Audit | no advisories (`--locked`) |
| Gitleaks | no leaks (3 accepted fingerprints in `.gitleaksignore`: LEAK-01, and LEAK-02 in history and in the working tree) |
| Trivy `--severity HIGH,CRITICAL --ignore-unfixed` | exit 0, **0 findings**. Without `--ignore-unfixed` the image has **175 HIGH/CRITICAL findings, all unfixed** (Debian 13 base) and the PHP packages have 0, so the strict policy fails with exit 1. |
| ZAP baseline (2.17.0) | exit 0: **0 FAIL, 10 WARN, 58 PASS** (about 50 s) |
| Production settings | pass |

The 10 ZAP WARN alerts are expected and documented: X-Powered-By (PHP's built-in server), nosniff on static css/js
(served by PHP's built-in server, outside Laravel), Permissions-Policy, COEP, cookie without HttpOnly (Laravel's
`XSRF-TOKEN`), non-storable content, session/authentication identification, big redirect, and PHP version flagged.

## Run the whole pipeline locally

`sh scripts/lab9-local-pipeline.sh` runs the same steps and gate as the workflow and prints a pass/fail summary
(reports in `reports/`, which is git-ignored; `gitleaks dir .` still scans those files and reports false positives from Trivy's CVE data, so delete `reports/*.json` before a working-tree scan, or use `gitleaks git .` as CI does). On Linux use `ZAP_TARGET=http://127.0.0.1:8000 ZAP_NETWORK="--network host"`.

## Seeded failures (Part G3)

`sh scripts/seed-lab9-failure.sh <case>` injects one failure, `... <case> --revert` removes it. Run it on a throwaway branch.

| Case | Injects | Verified result |
|---|---|---|
| `pest` | `OrderPolicy::view` returns true | 3 Pest tests fail; local pipeline gate fails on **Pest only** |
| `phpstan` | return-type error in a new class | PHPStan reports `return.type`, exit 1 |
| `composer` | pins guzzle back to 7.8.2 | `composer audit` exit 1 (9 advisories) |
| `gitleaks` | commits a fictional key to `config/seeded-secret.php` | Gitleaks exit 1, rule `generic-api-key`, redacted report |
| `zap` | stops sending the `Content-Security-Policy` header | ZAP exit 1 (`FAIL 10038` on 5 URLs); the production header test also fails |
| `trivy` | builds from `php:8.3.0-cli` | **not verified** (needs a rebuild and the old base image; expect fixable HIGH/CRITICAL findings) |

Note for `gitleaks`: the fixture is **committed** (a finding only exists in history), and reverting it does not remove
it from history (Part C5, point 5). For a clean build use a fresh branch.
Note for `phpstan`: a stale local result cache can hide files added from the host; the script clears it
(`rm -rf /tmp/phpstan` in the container). CI runners start without a cache.

## Defects in the lab text that the reference handles

1. **The workflow cannot run as written**: no skeleton in the repo, so `composer install`, `cp .env.example .env` and `php artisan` fail on a fresh checkout (the same cause as Lab 8). Pest and PHPStan run in the container instead.
2. **ZAP exits with code 2 on WARN alerts**, so the lab's gate would block on warnings, contradicting "WARN does not block". The reference passes `-I`, and `zap/baseline.conf` sets the FAIL rules. The reports folder must be writable by the ZAP user (`chmod 777 reports`).
3. **PHPStan needs a baseline.** Level 5 reports 11 findings on the existing code; without a baseline the clean pipeline is red.
4. **Trivy on this image is red by default** (175 unfixed HIGH/CRITICAL findings in the Debian base). The reference blocks on **fixable** HIGH/CRITICAL only and says so; the JSON still lists everything.
5. **Gitleaks install**: the lab's pinned 8.24.3 URL is replaced by 8.30.1 with SHA-256 verification against the release's checksums file (both assets confirmed to exist).
6. **The final gate** accepts only `success`: a skipped step (for example Trivy after a failed build) or a missing report fails the gate, as F1 requires, and it writes a table to the job summary.
7. **Production settings had no real check** in the lab; `ProductionConfigurationTest` adds one without printing secrets.

## A real finding the scan produced (fixed in the baseline)

ZAP's first run flagged `Content-Security-Policy` as missing on the 419 page (CSRF failure) and on 404s. `SecurityHeaders` was
appended to the end of the `web` middleware group, so responses produced earlier or outside the group never got the headers.
`bootstrap/app.php` now registers it as **global middleware, prepended**. Good discussion point: static analysis and unit tests
passed; only the dynamic scan found it.

## Branch protection (Part G5) — not done by the code

A red workflow does not block merging by itself. In the GitHub repository: **Settings → Rules → Rulesets → New branch ruleset**,
target `main`, enable **Require status checks to pass** and add the check **`application-security`** (the job name; it
appears in the list only after the workflow has run once), enable **Require a pull request before merging**. Verify with a PR
that has a seeded failure: the merge button must be disabled. Not verified from here.

## Dockerfile observations for D1 (discussion, no change made)

Base `php:8.3-cli` (Debian), the app runs as **root** (no `USER`), the PHP development server is the runtime (`artisan serve`),
port 8000 is exposed, no `HEALTHCHECK`, and the image is rebuilt with `composer create-project` (unpinned patch versions).
Trivy reports 0 findings in the PHP packages but 175 unfixed HIGH/CRITICAL in the OS packages, which is the point of Q6.

## Instructor materials (remove `solutions/`, `scripts/apply-*.sh`, `scripts/seed-*.sh`, `scripts/lab9-local-pipeline.sh` and `INSTRUCTOR_*` before distributing)

* `solutions/lab9/github/workflows/security.yml` — the final workflow. `sh scripts/apply-lab9-fixes.sh` installs it.
* `scripts/seed-lab9-failure.sh`, `scripts/lab9-local-pipeline.sh` — see above.

## Changes to earlier labs

`bootstrap/app.php`: `SecurityHeaders` is now global and prepended (see above). `.gitignore` now ignores `reports/*`.

**Status: verified (2026-11-22)** locally in the Docker container: every tool, the gate logic (all pass, failed tool, skipped step,
missing report), the seeded failures marked above, and the full local pipeline (pass on the clean baseline, a single FAIL with the
`pest` seed). **Not run:** the workflow on GitHub (the Linux runner, `--network host`, artifact upload), branch protection, the
`trivy` seed, and ZAP with Linux host networking.
