#!/bin/sh
# Instructor helper for Lab 9 Part G3: injects ONE seeded security failure, or reverts it.
#
#   sh scripts/seed-lab9-failure.sh <case>            inject
#   sh scripts/seed-lab9-failure.sh <case> --revert   undo
#
# Cases (the tool expected to fail is in brackets):
#   pest      weaken OrderPolicy so any user can view any order        [Pest]
#   phpstan   add a deliberate PHP return-type error                   [PHPStan]
#   composer  pin guzzle back to a version with known advisories       [Composer Audit]
#   gitleaks  commit a harmless secret-like fixture                    [Gitleaks]
#   trivy     build from an old PHP base image                         [Trivy]
#   zap       stop sending the Content-Security-Policy header          [ZAP FAIL rule 10038, and the header test]
#
# Run from the project root on the HOST, on a throwaway branch. Edits are made to the working tree;
# commit them yourself (except `gitleaks`, which must be committed to exist in history, so it commits for you).
set -e

CASE="$1"
ACTION="${2:-inject}"

case "$CASE" in
  pest)
    FILE=app/Policies/OrderPolicy.php
    if [ "$ACTION" = "--revert" ]; then git checkout -- "$FILE"; exit 0; fi
    python3 - "$FILE" <<'EOF'
import re, sys
p = sys.argv[1]
s = open(p).read()
s2 = re.sub(r"(public function view\(User \$user, Order \$order\): bool\s*\{\s*)return [^;]+;",
            r"\1return true; // SEEDED FAILURE: any user can view any order", s, count=1)
assert s != s2, "could not seed OrderPolicy::view"
open(p, "w").write(s2)
EOF
    ;;
  phpstan)
    FILE=app/Services/SeededTypeError.php
    # PHPStan keeps a result cache in the container; a stale one can hide files added from the host.
    clear_phpstan_cache() { docker compose exec -T app sh -c 'rm -rf /tmp/phpstan' || true; }
    if [ "$ACTION" = "--revert" ]; then rm -f "$FILE"; clear_phpstan_cache; exit 0; fi
    cat > "$FILE" <<'EOF'
<?php

declare(strict_types=1);

namespace App\Services;

// SEEDED FAILURE (Lab 9): PHPStan must reject this return type error.
class SeededTypeError
{
    public function totalCents(int $quantity, int $unitCents): int
    {
        return 'total: '.($quantity * $unitCents);
    }
}
EOF
    clear_phpstan_cache
    ;;
  composer)
    if [ "$ACTION" = "--revert" ]; then
        # Restore in place (not `git checkout`): composer.json/lock are single-file mounts and a replaced file
        # would leave the container looking at the old one.
        git show HEAD:composer.json > composer.json
        git show HEAD:composer.lock > composer.lock
        docker compose restart app
        exit 0
    fi
    docker compose exec -T app composer require 'guzzlehttp/guzzle:~7.8.2' \
        --no-blocking --no-install --no-scripts --no-plugins --no-audit --no-interaction
    docker compose restart app
    ;;
  gitleaks)
    FILE=config/seeded-secret.php
    if [ "$ACTION" = "--revert" ]; then
        git rm -q "$FILE"
        git commit -q -m "Revert seeded Gitleaks fixture (it stays in git history)"
        exit 0
    fi
    # The fictional key is assembled here so that THIS script holds no detectable secret literal
    # (otherwise Gitleaks would flag the instructor script itself).
    PART_ONE='Hq7wR2mT9vKx4Lp'
    PART_TWO='N8cYb3JdF6sUe5GaZ'
    cat > "$FILE" <<EOF
<?php

// SEEDED FAILURE (Lab 9): fictional key, not a real credential. Gitleaks must flag it.
return [
    'reporting_service_api_key' => '${PART_ONE}${PART_TWO}',
];
EOF
    git add "$FILE"
    git commit -q -m "Add reporting service configuration"
    ;;
  trivy)
    if [ "$ACTION" = "--revert" ]; then git checkout -- Dockerfile; exit 0; fi
    python3 - <<'EOF'
s = open("Dockerfile").read()
s2 = s.replace("FROM php:8.3-cli", "FROM php:8.3.0-cli", 1)
assert s != s2, "could not seed the Dockerfile base image"
open("Dockerfile", "w").write(s2)
EOF
    ;;
  zap)
    FILE=app/Http/Middleware/SecurityHeaders.php
    python3 - "$FILE" "$ACTION" <<'EOF'
import sys
p, action = sys.argv[1], sys.argv[2]
line = "$response->headers->set('Content-Security-Policy', $policy);"
marker = "// SEEDED FAILURE (Lab 9): "
s = open(p).read()
if action == "--revert":
    s2 = s.replace(marker + line, line, 1)
else:
    assert line in s and marker + line not in s, "could not seed SecurityHeaders"
    s2 = s.replace(line, marker + line, 1)
open(p, "w").write(s2)   # in place: keep the inode for the single-file bind mount
EOF
    ;;
  *)
    echo "usage: $0 {pest|phpstan|composer|gitleaks|trivy|zap} [--revert]"
    exit 2
    ;;
esac

echo "seeded: $CASE ($ACTION)"
