# Non-Docker Deployment Guide

Target OS: Ubuntu 22.04/24.04 LTS

## 1) Install base runtime

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx git curl unzip redis-server supervisor postgresql postgresql-contrib
```

Install PHP 8.3 + extension Laravel:

```bash
sudo apt install -y php8.3 php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl php8.3-zip
```

Install Node.js 24 + PM2:

```bash
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt install -y nodejs
sudo npm install -g pm2
```

Install Python:

```bash
sudo apt install -y python3 python3-venv python3-pip
```

## 2) PostgreSQL database setup

```bash
sudo -u postgres psql
CREATE DATABASE bakutrack;
CREATE USER bakutrack_user WITH PASSWORD 'change_me';
GRANT ALL PRIVILEGES ON DATABASE bakutrack TO bakutrack_user;
\q
```

## 3) Backend Laravel setup

```bash
cd /var/www/bakutrack/backend
cp .env.example .env
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Set `.env` minimum:

- `APP_ENV=production`
- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- `DB_DATABASE=bakutrack`
- `DB_USERNAME=bakutrack_user`
- `DB_PASSWORD=change_me`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `BAKUTRACK_INTERNAL_KEY=<strong-secret>`

## 4) Frontend Next.js setup

```bash
cd /var/www/bakutrack/frontend
cp .env.example .env
npm install
npm run build
pm2 start npm --name bakutrack-next -- start
pm2 save
pm2 startup
```

Set `NEXT_PUBLIC_API_BASE_URL` ke URL API production.

## 5) Scraper service setup

```bash
cd /var/www/bakutrack/scraper
cp .env.example .env
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -m playwright install chromium
```

Run manual:

```bash
source .venv/bin/activate
uvicorn src.main:app --host 0.0.0.0 --port 9000
```

## 6) systemd services

Copy file dari folder `ops/systemd` ke `/etc/systemd/system/`, lalu:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now bakutrack-queue.service
sudo systemctl enable --now bakutrack-next.service
sudo systemctl enable --now bakutrack-scraper.service
```

## 7) Nginx reverse proxy

- API Laravel: gunakan `ops/nginx/bakutrack-api.conf`
- Frontend Next.js: gunakan `ops/nginx/bakutrack-app.conf`

```bash
sudo ln -s /etc/nginx/sites-available/bakutrack-api.conf /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/bakutrack-app.conf /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

## 8) n8n automation

- Import `n8n/bakutrack_daily_workflow.json`.
- Set n8n env variable:
  - `SCRAPER_BASE_URL`
  - `BACKEND_BASE_URL`
  - `BAKUTRACK_INTERNAL_KEY`
  - `FONNTE_API_KEY`

## 9) Health checks

- Laravel: `GET /up`
- Scraper: `GET http://127.0.0.1:9000/health`
- Next.js: `GET /`
- Redis: `redis-cli ping`
