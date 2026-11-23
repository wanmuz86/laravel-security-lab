# Software Supply Chain Security & Remediation Report (reference answer)

Application: SecureProject (Laravel 12, PHP 8.3) · Assessed: 2026-11-22 · Branch: `security/dependency-remediation`

## 1. Baseline

| Metric | Value |
|---|---|
| Composer validation | Valid |
| Known vulnerable packages | 2 (16 advisories: 1 high, 13 medium, 2 low) |
| Outdated direct packages | 7 |
| Application tests | Passing (202) |
| Secret findings (Gitleaks) | 2 in history, 1 in working tree |
| SBOM | None |

## 2. Vulnerability assessment

| ID | Package | Locked | Advisories | Severity | Production? | Reachable? | Decision |
|---|---|---|---|---|---|---|---|
| VULN-01 | `guzzlehttp/guzzle` | 7.8.2 | 9, e.g. CVE-2026-69246 (host check bypass) | High (1), Medium (8) | Yes (`--no-dev` still reports it) | Yes, via Laravel's HTTP client, which performs outbound calls | **Remediated**: `composer require guzzlehttp/guzzle:^7.8.2` → 7.16.5. Pin `~7.8.2` was blocking the fix. |
| VULN-02 | `dompdf/dompdf` (+4 transitive) | 2.0.3 | 7, e.g. CVE-2023-50262, CVE-2026-59943 | Medium (5), Low (2) | Yes | No: no reference in `app/`, `routes/`, `resources/` | **Remediated by removal** (`composer remove dompdf/dompdf`). Nothing in 2.x is fixed (affected `<3.1.6`), and the code never used it. |

Prioritisation beyond CVSS: guzzle first (high severity, reachable, patch available in the same major line); dompdf second (not reachable, but removing it also removes four transitive packages).
OWASP Top 10:2025 mapping: A03 Software Supply Chain Failures.

## 3. Remediation

1. `composer require guzzlehttp/guzzle:^7.8.2` (7.8.2 → 7.16.5), `composer remove dompdf/dompdf`.
2. `composer audit` → no advisories; `php artisan test` → 209 passing; `composer validate --strict` → valid.
3. `composer require --dev cyclonedx/cyclonedx-php-composer` after verifying the publisher and allowing the plugin; SBOM generated to `reports/sbom.json` (CycloneDX 1.5, 135 components).
4. `.github/dependabot.yml` (composer weekly, grouped minor/patch; github-actions weekly) and `.github/workflows/dependency-security.yml` (`contents: read`, `composer validate --strict`, `composer audit --locked`, Docker-based tests).
5. `.gitignore` excludes `.env*` except `.env.example`; `.env.example` holds placeholders only.

## 4. Secret scan

| ID | File | Rule | Location | Type | Action |
|---|---|---|---|---|---|
| LEAK-01 | `config/services.php` | generic-api-key | commit `5f414a4`, removed in `19a865a` | Payment gateway key (fictional) | Treat as compromised: rotate (n/a, fictional), moved to `PAYMENT_GATEWAY_API_KEY`, fingerprint accepted in `.gitleaksignore`. In a real incident purge history with `git filter-repo` on a clone and force-push. |
| LEAK-02 | `docker/env.lab` | generic-api-key | commit `6459690` + working tree | Local training webhook secret | Accepted: used only between local containers; documented in `.gitleaksignore`. |

## 5. Results

| Metric | Baseline | Final | Status |
|---|---|---|---|
| Known vulnerable packages | 2 | 0 | Resolved |
| Outdated direct packages | 7 | 6 (all major-version jumps: framework, tinker, pest, pest-plugin-laravel, phpunit, guzzle 8) | Improved |
| Composer validation | Valid | Valid | Pass |
| Application tests | 202 passing | 209 passing | Pass |
| Unaccepted secret leaks | 2 (history) | 0 | Remediated / accepted with justification |
| SBOM generated | No | Yes (`reports/sbom.json`) | Complete |

## 6. Remediation register

| Ref | Finding | Action | Status | Residual risk |
|---|---|---|---|---|
| SEC-01 | guzzle 7.8.2 advisories | Constraint changed to `^7.8.2`, updated to 7.16.5 | Closed | None known; stays on 7.x (8.x is a major upgrade, tracked by Dependabot) |
| SEC-02 | dompdf 2.0.3 advisories | Removed (unused) | Closed | None |
| SEC-03 | Hard-coded gateway key in history | Moved to env; fingerprint accepted | Closed (accepted) | The value remains in git history; fictional here |
| SEC-04 | No automated dependency/CI checks | Dependabot + audit workflow | Closed | Workflow not yet run on GitHub; pin action SHAs for production |
| SEC-05 | `.env` not ignored | `.gitignore` + sanitized `.env.example` | Closed | None |
