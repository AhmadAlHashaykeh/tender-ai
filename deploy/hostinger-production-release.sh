#!/usr/bin/env bash
# TenderAI — production release on Hostinger (PHP 8.2 CLI only)
# Run on server: bash deploy/hostinger-production-release.sh
set -euo pipefail

APP_DIR="/home/u319040066/domains/ahmadalhashaykeh.com/public_html/tenderai"
PHP_BIN="/opt/alt/php82/usr/bin/php"
EXPECTED_COMMIT="${1:-f978629}"

log() { printf '\n==> %s\n' "$*"; }

cd "${APP_DIR}"

log "Git pull origin main"
git fetch origin main
git pull origin main

CURRENT="$(git rev-parse --short HEAD)"
log "Current commit: ${CURRENT}"
if [[ "${CURRENT}" != "${EXPECTED_COMMIT}" ]] && ! git merge-base --is-ancestor "${EXPECTED_COMMIT}" HEAD 2>/dev/null; then
  echo "WARNING: HEAD (${CURRENT}) may not include expected commit ${EXPECTED_COMMIT}. Verify git pull succeeded."
fi

log "Composer install (PHP 8.2)"
if command -v composer >/dev/null 2>&1; then
  COMPOSER_BIN="$(command -v composer)"
elif command -v composer2 >/dev/null 2>&1; then
  COMPOSER_BIN="$(command -v composer2)"
else
  echo "ERROR: composer not found in PATH. Install or set COMPOSER_BIN."
  exit 1
fi
"${PHP_BIN}" "${COMPOSER_BIN}" install --no-dev --optimize-autoloader --no-interaction

log "Migrations"
"${PHP_BIN}" artisan migrate --force

log "Laravel cache"
"${PHP_BIN}" artisan optimize:clear
"${PHP_BIN}" artisan config:cache
"${PHP_BIN}" artisan route:cache
"${PHP_BIN}" artisan view:cache

log "Verify imports commands"
"${PHP_BIN}" artisan list | grep -E '^  imports' || true

log "Pipeline diagnostics"
"${PHP_BIN}" artisan imports:diagnose

log "Process pending queue jobs"
"${PHP_BIN}" artisan queue:process-pending --max-jobs=25 --timeout=120

log "Refresh market statistics"
"${PHP_BIN}" artisan stats:refresh

log "Scheduler"
"${PHP_BIN}" artisan schedule:list

log "Production release finished"
echo "Commit: $(git rev-parse HEAD)"
echo "Cron (hPanel): * * * * * ${PHP_BIN} ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1"
