# BakuTrack

Platform intelijen harga bahan baku untuk UMKM F&B, katering, warung, dan bisnis kuliner lokal.  
BakuTrack membantu memantau harga harian dari berbagai sumber, menemukan peluang harga lebih murah, dan mengirim notifikasi cepat.

## Kenapa BakuTrack

- Pantau bahan baku secara dinamis berdasarkan `watchlist` (tidak statis per komoditas tertentu).
- Ambil data dari sumber resmi + marketplace fallback (`hybrid` source).
- Deteksi anomali harga agar alert tidak melenceng.
- Dashboard modern untuk observasi harga terbaru, alert, dan aksi cepat.
- Siap non-Docker (lokal/server biasa) dengan biaya infrastruktur ringan.

## Stack Utama

- Frontend: `Next.js 16` + `Tailwind CSS 4`
- Backend API: `Laravel 13` + `Sanctum`
- Scraper Engine: `Python` (`FastAPI`, `Playwright`, `httpx`, `BeautifulSoup`)
- Database: `PostgreSQL`
- Cache/Queue: `Redis`
- LLM lokal (opsional): `Ollama` + model `qwen2.5:1.5b`
- Workflow automation: `n8n` (file workflow sudah disiapkan)

## Arsitektur Singkat

1. User membuat watchlist dari dashboard.
2. Backend membuat job scraping (`dispatch`).
3. Scraper menjalankan provider chain (`pihps`, `tokopedia`, `ralali`, `gudangada`) sesuai mode `hybrid`.
4. Hasil scrape di-ingest ke backend, dinormalisasi, dicek anomali, lalu disimpan.
5. Dashboard menampilkan observasi terbaru dan alert.
6. Notifikasi WA bisa diproses melalui endpoint internal + n8n.

## Struktur Folder

```text
backend/   -> Laravel API, auth, watchlist, alert, ingest
frontend/  -> Next.js dashboard
scraper/   -> Python scraper service
n8n/       -> Workflow JSON siap import
docs/      -> Dokumen arsitektur + deployment
ops/       -> Template ops (nginx/supervisor/systemd)
scripts/   -> Helper script (scrape cycle, setup LLM lokal)
```

---

## Prasyarat

### Wajib

- PHP `>= 8.3`
- Composer `>= 2`
- Node.js `>= 20`
- npm `>= 10`
- Python `>= 3.11` (disarankan 3.12)
- PostgreSQL `>= 14`
- Redis `>= 6`

### Opsional (disarankan)

- Ollama (untuk normalisasi nama produk via LLM lokal)

---

## Instalasi (Windows, Tanpa Docker)

> Semua contoh command di bawah dijalankan dari root project:
> `D:\proyekfahri\BakuTrack`

## 1) Setup PostgreSQL & Redis

- Pastikan PostgreSQL dan Redis sudah terinstall dan running.
- Buat database PostgreSQL, contoh:

```sql
CREATE DATABASE bakutrack;
```

## 2) Setup Backend (Laravel)

```powershell
cd D:\proyekfahri\BakuTrack\backend
copy .env.example .env
composer install
php artisan key:generate
```

Edit file `backend/.env`:

- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- `DB_DATABASE=bakutrack`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`
- `REDIS_HOST=127.0.0.1`
- `REDIS_PORT=6379`
- `BAKUTRACK_INTERNAL_KEY=<isi-random-aman>`

Jalankan migrasi + seed:

```powershell
php artisan migrate --force
php artisan db:seed --force
```

Jalankan backend API:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

## 3) Setup Frontend (Next.js)

```powershell
cd D:\proyekfahri\BakuTrack\frontend
copy .env.example .env
npm install
npm run dev
```

Frontend default:

- [http://127.0.0.1:3000](http://127.0.0.1:3000)

## 4) Setup Scraper (Python)

```powershell
cd D:\proyekfahri\BakuTrack\scraper
copy .env.example .env
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
python -m playwright install chromium
```

Pastikan `scraper/.env`:

- `BACKEND_BASE_URL=http://127.0.0.1:8000`
- `BACKEND_INTERNAL_KEY=<harus sama dengan backend/.env>`

Jalankan scraper service:

```powershell
uvicorn src.main:app --host 0.0.0.0 --port 9000
```

Health check scraper:

- [http://127.0.0.1:9000/health](http://127.0.0.1:9000/health)

## 5) Setup LLM Lokal (Opsional)

Dari root project:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup_local_llm.ps1 -Model qwen2.5:1.5b
```

Jika aktif, backend akan memakai Ollama saat OpenAI key tidak diisi.

---

## Menjalankan Semua Service (Ringkas)

Buka 3 terminal terpisah:

1. Backend

```powershell
cd D:\proyekfahri\BakuTrack\backend
php artisan serve --host=127.0.0.1 --port=8000
```

2. Frontend

```powershell
cd D:\proyekfahri\BakuTrack\frontend
npm run dev
```

3. Scraper

```powershell
cd D:\proyekfahri\BakuTrack\scraper
.\.venv\Scripts\Activate.ps1
uvicorn src.main:app --host 0.0.0.0 --port 9000
```

---

## Cara Pakai (MVP)

## 1) Login / token

- Register/login lewat API, atau gunakan akun seed:
  - Email: `owner@bakutrack.local`
  - Password: `password`
- Simpan token ke dashboard (field `API Token`).

Contoh login via API:

```bash
POST /api/v1/auth/login
{
  "email": "owner@bakutrack.local",
  "password": "password"
}
```

## 2) Tambah watchlist

- Isi bahan baku di dashboard, misalnya: `susu uht`, `sedotan`, `cup gelas`, dll.
- Klik `Simpan Watchlist`.

## 3) Trigger scraping manual

- Dari dashboard: klik `Scrape Sekarang`
- Atau via script:

```powershell
cd D:\proyekfahri\BakuTrack
powershell -ExecutionPolicy Bypass -File .\scripts\run_scrape_cycle.ps1 -Source hybrid
```

## 4) Lihat observasi dan alert

- Dashboard akan menampilkan observasi terbaru per watchlist.
- Alert akan muncul jika rule drop/arbitrage terpenuhi.

---

## Endpoint Penting (MVP)

Public:

- `POST /api/v1/auth/register`
- `POST /api/v1/auth/login`

Protected (`Bearer token`):

- `GET /api/v1/me`
- `GET /api/v1/dashboard/summary`
- `GET /api/v1/watchlists`
- `POST /api/v1/watchlists`
- `PATCH /api/v1/watchlists/{id}`
- `DELETE /api/v1/watchlists/{id}`
- `POST /api/v1/scrape/trigger`
- `GET /api/v1/alerts`
- `POST /api/v1/alerts/{id}/ack`

Internal:

- `POST /api/v1/internal/scrape/dispatch`
- `POST /api/v1/internal/scrape/results`
- `POST /api/v1/internal/scrape/job-status`

---

## Maintenance & Operasional

Prune data lama:

```powershell
cd D:\proyekfahri\BakuTrack\backend
php artisan bakutrack:prune-data --raw-days=30 --observation-days=180 --job-days=30
```

Clear cache:

```powershell
php artisan cache:clear
```

Lint frontend:

```powershell
cd D:\proyekfahri\BakuTrack\frontend
npm run lint
```

---

## Troubleshooting

### Backend `500` saat scraper dispatch/ingest

- Cek `BAKUTRACK_INTERNAL_KEY` backend dan scraper harus sama.
- Cek log: `backend/storage/logs/laravel.log`

### Scraper `ReadTimeout`

- Pastikan backend hidup.
- Timeout default scraper sudah dinaikkan (`SCRAPER_TIMEOUT_SECONDS=90`).
- Coba ulang scrape cycle.

### Frontend tidak menampilkan data

- Pastikan token valid (Sanctum).
- Pastikan API base URL benar: `NEXT_PUBLIC_API_BASE_URL=http://127.0.0.1:8000/api/v1`
- Klik `Refresh Data` di dashboard.

### Playwright error browser

- Jalankan ulang:

```powershell
python -m playwright install chromium
```

---

## Dokumen Tambahan

- Arsitektur: [docs/architecture/technical-architecture.md](docs/architecture/technical-architecture.md)
- API MVP: [docs/architecture/api-mvp.md](docs/architecture/api-mvp.md)
- Deployment non-Docker: [docs/deployment/non-docker.md](docs/deployment/non-docker.md)
- Workflow n8n: [n8n/bakutrack_daily_workflow.json](n8n/bakutrack_daily_workflow.json)

---
