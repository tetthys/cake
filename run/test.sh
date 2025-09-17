#!/usr/bin/env bash
# Purpose: Run tetthys/cake test suite (Pest) inside Docker
# Usage:   bash ./run/test.sh              # run all tests
#          bash ./run/test.sh -p Engine    # pass extra args to Pest (e.g., --filter / -p)
#
# Notes:
# - Uses docker compose with the `composer:2` image which includes PHP + Composer.
# - Installs dependencies if vendor/ is missing (or when you force with --fresh).
# - Exits non-zero on failure.
# - Keeps host machine clean; everything runs in the container.

set -euo pipefail

# Resolve repo root (directory that contains this script is ./run/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Detect docker compose command (v2 `docker compose` vs legacy `docker-compose`)
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DC="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DC="docker-compose"
else
  echo "[ERROR] Docker Compose is not installed." >&2
  exit 1
fi

# Extra args are forwarded to Pest (e.g., --filter, -p, --coverage, etc.)
PEST_ARGS=("$@")

cd "${REPO_ROOT}"

# Pull (optional but nice to keep images current)
${DC} pull app >/dev/null 2>&1 || true

# Composer install (idempotent). We run it in the container to avoid host PHP drift.
${DC} run --rm app sh -lc '
  set -e
  if [ ! -f composer.json ]; then
    echo "[ERROR] composer.json not found in /app" >&2
    exit 2
  fi

  # Explicitly allow pestphp/pest-plugin (required for Composer 2.2+ security)
  composer config --no-plugins allow-plugins.pestphp/pest-plugin true

  # Install dependencies; prefer dist to speed up and reduce git usage
  if [ ! -d vendor ]; then
    echo "[INFO] Installing dependencies..."
    composer install --no-progress --prefer-dist
  else
    echo "[INFO] Dependencies already present. Run \`rm -rf vendor\` to force reinstall."
  fi

  if [ ! -x vendor/bin/pest ]; then
    echo "[ERROR] vendor/bin/pest not found (dev requirements missing?)." >&2
    exit 3
  fi
'

# Run tests (forward all user-supplied args to Pest)
${DC} run --rm app sh -lc "
  set -e
  ./vendor/bin/pest --colors=always ${PEST_ARGS[@]+"${PEST_ARGS[@]}"}
"
