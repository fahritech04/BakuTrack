# BakuTrack Technical Architecture (MVP)

## Service Topology

- `frontend` (Next.js + Tailwind): Dashboard web untuk owner UMKM.
- `backend` (Laravel + Sanctum): Core API, tenancy, billing, alert rules, ingest scraping.
- `scraper` (Python FastAPI + Playwright): Microservice ekstraksi data marketplace.
- `n8n`: Orkestrasi cron -> scrape cycle -> kirim WhatsApp -> update status.
- `PostgreSQL`: Primary relational storage.
- `Redis`: Queue dan cache hot dashboard.

## High-Level Data Flow

1. User menambah bahan baku di `watchlists` via dashboard.
2. `n8n` cron memanggil endpoint scraper dispatch cycle.
3. Scraper meminta job ke Laravel (`/api/v1/internal/scrape/dispatch`).
4. Scraper scrape tiap source marketplace.
5. Hasil dikirim ke Laravel (`/api/v1/internal/scrape/results`).
6. Laravel simpan raw + normalisasi + deteksi anomali + evaluasi alert.
7. Jika trigger terpenuhi, `notification_logs` dibuat status `pending`.
8. `n8n` mengambil pending notification dan kirim ke WhatsApp API.
9. `n8n` update status `sent/failed` ke Laravel.

## n8n Workflow (Text Diagram)

```text
[Cron Trigger harian]
  -> [HTTP POST scraper /scrape/dispatch-cycle]
  -> [HTTP GET backend /internal/notifications/pending]
  -> [Split notification item]
  -> [Prepare WA payload]
  -> [HTTP Send WhatsApp API]
  -> [IF success]
       -> true: [HTTP POST mark sent]
       -> false: [HTTP POST mark failed]
```

## Database High-Level Schema

- `tenants`: organisasi UMKM.
- `users`: akun dan role (`owner/staff/admin`) dengan `tenant_id`.
- `watchlists`: daftar bahan baku dan threshold alert.
- `product_masters`: kamus produk standar.
- `product_aliases`: alias marketplace -> produk standar.
- `suppliers`: profil supplier lintas source.
- `scrape_jobs`: jadwal/status job scraping.
- `scrape_result_raws`: data mentah hasil scraping.
- `price_observations`: harga normalisasi per waktu.
- `price_daily_stats`: agregasi harian harga.
- `alerts`: price-drop/arbitrage/anomaly-block.
- `notification_logs`: antrean dan status notifikasi WA.
- `subscriptions`: paket langganan tenant.
- `billing_events`: event pembayaran/webhook billing.

## AI Integration Points

- `ProductNormalizationService`: alias lookup -> keyword -> LLM fallback.
- `AnomalyDetectionService`: median + MAD guardrail untuk block nilai abnormal.
- `AlertEvaluationService`: rule-based trigger (`drop_threshold_pct`, `arbitrage_threshold_pct`).
- Fase lanjutan: model forecasting time-series untuk rekomendasi beli.
