# BakuTrack Backend (Laravel)

Core API untuk BakuTrack:

- Auth (Sanctum token)
- Multi-tenant watchlist & alert
- Price ingest endpoint untuk scraper
- Internal endpoint untuk n8n notification flow

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

## Test

```bash
php artisan test
```

## Internal Security

Endpoint `/api/v1/internal/*` wajib header:

- `X-Internal-Key: <BAKUTRACK_INTERNAL_KEY>`
