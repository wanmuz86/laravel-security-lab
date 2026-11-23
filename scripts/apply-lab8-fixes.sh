#!/bin/sh
# Instructor/after-lab helper for Lab 8: copies the reference solution from ./solutions/lab8 into place
# (remediated composer.json/composer.lock, Dependabot + CI workflow, .gitignore, .env.example,
# .gitleaksignore, the Pest guard tests), re-syncs vendor/ inside the container, generates the reports
# (audit JSON, CycloneDX SBOM, Gitleaks) into ./reports and runs the tests.
# Run from the project root on the HOST. Needs network access (Composer) and, for the last
# report, gitleaks on the host (brew install gitleaks).
set -e

S=solutions/lab8

cp $S/composer.json composer.json
cp $S/composer.lock composer.lock

mkdir -p .github/workflows reports tests/Feature/Security
cp $S/github/dependabot.yml                    .github/dependabot.yml
cp $S/github/workflows/dependency-security.yml .github/workflows/dependency-security.yml
cp $S/gitignore                                .gitignore
cp $S/env.example                              .env.example
[ -f $S/gitleaksignore ] && cp $S/gitleaksignore .gitleaksignore
cp $S/tests/SupplyChainTest.php                tests/Feature/Security/SupplyChainTest.php

# The entrypoint runs `composer install`, which syncs vendor/ with the new lock (removes dompdf, adds the SBOM plugin).
docker compose restart app
sleep 25

docker compose exec -T app composer validate --strict
docker compose exec -T app sh -c 'composer audit --format=json > reports/composer-audit-final.json; composer audit'
docker compose exec -T app composer cyclonedx:make-sbom --output-format=JSON --output-file=reports/sbom.json
docker compose exec -T app php -r "json_decode(file_get_contents('reports/sbom.json'), true, 512, JSON_THROW_ON_ERROR); echo 'sbom.json: valid JSON', PHP_EOL;"

if command -v gitleaks >/dev/null 2>&1; then
    gitleaks git . --redact --no-banner --report-format json --report-path reports/gitleaks-final.json \
        && echo "gitleaks: no unaccepted leaks"
else
    echo "gitleaks not installed on the host: skipped (brew install gitleaks)"
fi

docker compose exec -T app php artisan test
