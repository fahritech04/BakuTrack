# BakuTrack Scraper Service

Python scraping microservice (FastAPI + Playwright) untuk mengambil harga bahan baku dari source marketplace.

## Setup

```bash
cp .env.example .env
python -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
python -m playwright install chromium
```

## Run API

```bash
uvicorn src.main:app --host 0.0.0.0 --port 9000
```

## Run one dispatch cycle

```bash
python -m src.runner --source ralali
```

## Endpoints

- `GET /health`
- `POST /scrape/job`
- `POST /scrape/dispatch-cycle`
