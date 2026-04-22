# MVP API Endpoints

Base URL: `/api/v1`

## Auth

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout` (auth:sanctum)
- `GET /me` (auth:sanctum)

## Dashboard

- `GET /dashboard/summary`

## Watchlist

- `GET /watchlists`
- `POST /watchlists`
- `GET /watchlists/{watchlist}`
- `PATCH /watchlists/{watchlist}`
- `DELETE /watchlists/{watchlist}`

## Prices

- `GET /prices/latest`
- `GET /prices/history`

## Alerts

- `GET /alerts`
- `POST /alerts/{alert}/ack`

## Subscription/Billing

- `GET /subscription`
- `POST /billing/webhook`

## Internal (n8n/scraper)

Wajib header `X-Internal-Key`:

- `POST /internal/scrape/dispatch`
- `POST /internal/scrape/results`
- `GET /internal/notifications/pending`
- `POST /internal/notifications/{notification}/status`
- `POST /internal/notifications/wa/webhook`
