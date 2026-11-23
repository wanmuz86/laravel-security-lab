#!/bin/sh
# Runs the Lab 9 security pipeline locally, in the same order as .github/workflows/security.yml, and prints a
# gate summary. Use it to check a change (or a seeded failure) before pushing. Reports go to ./reports.
# Needs: Docker, gitleaks on the host (brew install gitleaks). The first run downloads the Trivy and ZAP images.
# Run from the project root on the HOST. Exit status 1 means at least one gate failed.
TRIVY_IMAGE=${TRIVY_IMAGE:-aquasec/trivy:0.76.2}
ZAP_IMAGE=${ZAP_IMAGE:-ghcr.io/zaproxy/zaproxy:2.17.0}
# macOS/Windows Docker Desktop reach the host through host.docker.internal; on Linux use: ZAP_TARGET=http://127.0.0.1:8000 ZAP_NETWORK="--network host"
ZAP_TARGET=${ZAP_TARGET:-http://host.docker.internal:8000}
ZAP_NETWORK=${ZAP_NETWORK:-}

rm -rf reports/*.json reports/*.xml reports/*.html reports/*.conf reports/*.yaml reports/*.log
mkdir -p reports && chmod 777 reports
RESULTS=""
FAILED=0
run() { # run <name> <report-or-empty> <command...>
    name="$1"; report="$2"; shift 2
    if "$@"; then ok=1; else ok=0; fi
    if [ "$ok" = 1 ] && [ -n "$report" ] && [ ! -s "reports/$report" ]; then ok=0; echo "  (no report: reports/$report)"; fi
    if [ "$ok" = 1 ]; then RESULTS="$RESULTS\n  pass  $name"; else RESULTS="$RESULTS\n  FAIL  $name"; FAILED=1; fi
}
app() { docker compose exec -T app "$@"; }

run "Composer Audit" composer-audit.json sh -c 'docker compose exec -T app composer audit --locked --no-interaction --format=json > reports/composer-audit.json'
if command -v gitleaks >/dev/null 2>&1; then
    run "Gitleaks" gitleaks.json gitleaks git . --redact --no-banner --report-format json --report-path reports/gitleaks.json
else
    RESULTS="$RESULTS\n  FAIL  Gitleaks (not installed on the host)"; FAILED=1
fi
run "Container build" "" docker build -q -t laravel-security-lab:ci .
run "Trivy" trivy.json docker run --rm -v /var/run/docker.sock:/var/run/docker.sock -v "$PWD/reports:/reports" "$TRIVY_IMAGE" image --quiet --severity HIGH,CRITICAL --ignore-unfixed --exit-code 1 --format json --output /reports/trivy.json laravel-security-lab:ci
docker compose up -d --build >/dev/null 2>&1
for i in $(seq 1 60); do curl --fail --silent http://localhost:8000/up >/dev/null && break; sleep 3; done
run "Pest security tests" pest-junit.xml app php artisan test tests/Feature/Security --log-junit=reports/pest-junit.xml
run "PHPStan" phpstan.json sh -c 'docker compose exec -T app vendor/bin/phpstan analyse --no-progress --memory-limit=1G --error-format=json > reports/phpstan.json 2>/dev/null'
run "Production settings" production-config-junit.xml app php artisan test tests/Feature/Security/ProductionConfigurationTest.php --log-junit=reports/production-config-junit.xml
cp zap/baseline.conf reports/zap-baseline.conf
run "OWASP ZAP baseline" zap-report.json docker run --rm $ZAP_NETWORK -v "$PWD/reports:/zap/wrk:rw" "$ZAP_IMAGE" zap-baseline.py -t "$ZAP_TARGET" -c zap-baseline.conf -I -r zap-report.html -J zap-report.json

printf "\nSecurity gate results:%b\n" "$RESULTS"
[ "$FAILED" = 0 ] && echo "GATE: PASS" || echo "GATE: FAIL (see reports/)"
exit "$FAILED"
