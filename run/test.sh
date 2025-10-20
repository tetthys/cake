#!/usr/bin/env bash
set -Eeuo pipefail

echo "🧪 Running tetthys/cake tests inside PHP 8.3 container..."

# Build php service (uses local Dockerfile)
docker compose build php

# Run composer and tests inside the container
docker compose run --rm php bash -lc '
  set -Eeuo pipefail

  # 1) Fix git "dubious ownership" for bind-mounted /app
  git config --global --add safe.directory /app || true

  # 2) Silence root-version warning (treat this repo as dev-main)
  export COMPOSER_ROOT_VERSION="${COMPOSER_ROOT_VERSION:-dev-main}"

  php -v
  composer --version

  # 3) Optional sanity check (does not fail build if warnings exist)
  composer validate --no-check-publish --no-check-lock --ansi || true

  # 4) Decide whether to install or update
  NEED_UPDATE=0
  if [ ! -f composer.lock ]; then
    NEED_UPDATE=1
  else
    # If testbench is not present in lock, we must update
    if ! grep -q "\"orchestra/testbench\"" composer.lock; then
      NEED_UPDATE=1
    fi
  fi

  if [ "${NEED_UPDATE}" = "1" ]; then
    echo "🔧 composer.lock is missing/outdated. Running composer update..."
    composer update --no-interaction --prefer-dist --ansi
  else
    echo "📦 Installing from lock..."
    composer install --no-interaction --prefer-dist --ansi
  fi

  # 5) Run tests (Pest)
  ./vendor/bin/pest --colors=always -v
'
