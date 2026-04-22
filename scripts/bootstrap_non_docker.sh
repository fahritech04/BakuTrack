#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "[1/5] Backend dependencies"
cd "$ROOT_DIR/backend"
composer install --no-dev --optimize-autoloader

if [ ! -f .env ]; then
  cp .env.example .env
fi

php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force


echo "[2/5] Frontend dependencies"
cd "$ROOT_DIR/frontend"
npm install
npm run build


echo "[3/5] Scraper dependencies"
cd "$ROOT_DIR/scraper"
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -m playwright install chromium


echo "[4/5] n8n workflow"
echo "Import file: $ROOT_DIR/n8n/bakutrack_daily_workflow.json"


echo "[5/5] Done"
echo "Read docs/deployment/non-docker.md for production service setup."
