#!/usr/bin/env bash
# TenderAI — Hostinger deployment (run on server via SSH)
set -euo pipefail

DEPLOY_ROOT="/home/u319040066/domains/ahmadalhashaykeh.com/public_html"
APP_DIR="${DEPLOY_ROOT}/tenderai"
REPO="https://github.com/AhmadAlHashaykeh/tender-ai.git"
BRANCH="main"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

log() { printf '\n==> %s\n' "$*"; }

cd "${DEPLOY_ROOT}"

if [[ -d "${APP_DIR}/.git" ]]; then
  log "Updating existing clone"
  cd "${APP_DIR}"
  git fetch origin
  git checkout "${BRANCH}"
  git pull origin "${BRANCH}"
else
  log "Cloning repository"
  git clone "${REPO}" tenderai
  cd "${APP_DIR}"
fi

log "Installing PHP dependencies"
${COMPOSER_BIN} install --no-dev --optimize-autoloader --no-interaction

if [[ -f deploy/hostinger.env ]]; then
  # shellcheck disable=SC1091
  source deploy/hostinger.env
fi

APP_URL="${APP_URL:-https://ahmadalhashaykeh.com/tenderai}"
VITE_BASE_PATH="${VITE_BASE_PATH:-/tenderai/public/}"

log "Installing and building frontend assets"
export VITE_BASE_PATH
if command -v npm >/dev/null 2>&1; then
  npm ci 2>/dev/null || npm install
  npm run build
else
  echo "WARNING: npm not found — skip frontend build or install Node on Hostinger."
fi

if [[ ! -f .env ]]; then
  log "Creating .env from .env.example"
  cp .env.example .env
fi

log "Configuring .env for production"
php deploy/patch-env.php \
  --app-url="${APP_URL}" \
  --db-host="${DB_HOST:-127.0.0.1}" \
  --db-port="${DB_PORT:-3306}" \
  --db-database="${DB_DATABASE:-}" \
  --db-user="${DB_USERNAME:-}" \
  --db-password="${DB_PASSWORD:-}"

log "Applying public/.htaccess RewriteBase for subfolder"
php deploy/patch-htaccess.php --base="/tenderai/public/"

log "Generating application key (if missing)"
if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  ${PHP_BIN} artisan key:generate --force
fi

log "Running migrations"
${PHP_BIN} artisan migrate --force

log "Storage link"
${PHP_BIN} artisan storage:link || true

log "Optimizing Laravel"
${PHP_BIN} artisan optimize:clear
${PHP_BIN} artisan config:cache
${PHP_BIN} artisan route:cache
${PHP_BIN} artisan view:cache

log "Setting directory permissions"
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

log "Deployment finished"
echo "URL: ${APP_URL}"
echo "Cron (add in hPanel if not set):"
echo "* * * * * /usr/bin/php ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1"
