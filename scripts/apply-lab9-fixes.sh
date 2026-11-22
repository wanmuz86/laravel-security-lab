#!/bin/sh
# Instructor/after-lab helper for Lab 9: installs the reference security workflow from ./solutions/lab9 and validates it.
# Run from the project root on the HOST. (The scans themselves run on GitHub, or locally with scripts/lab9-local-pipeline.sh.)
set -e

mkdir -p .github/workflows
cp solutions/lab9/github/workflows/security.yml .github/workflows/security.yml
python3 -c "import yaml; yaml.safe_load(open('.github/workflows/security.yml')); print('security.yml: valid YAML')"
echo "Installed .github/workflows/security.yml. Run the pipeline locally with: sh scripts/lab9-local-pipeline.sh"
