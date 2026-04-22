from __future__ import annotations

import os
from dataclasses import dataclass

from dotenv import load_dotenv

load_dotenv()


@dataclass(frozen=True)
class Settings:
    scraper_host: str = os.getenv("SCRAPER_HOST", "0.0.0.0")
    scraper_port: int = int(os.getenv("SCRAPER_PORT", "9000"))
    scraper_log_level: str = os.getenv("SCRAPER_LOG_LEVEL", "info")

    backend_base_url: str = os.getenv("BACKEND_BASE_URL", "http://127.0.0.1:8000")
    backend_internal_key: str = os.getenv("BACKEND_INTERNAL_KEY", "")
    backend_dispatch_endpoint: str = os.getenv(
        "BACKEND_DISPATCH_ENDPOINT", "/api/v1/internal/scrape/dispatch"
    )
    backend_ingest_endpoint: str = os.getenv(
        "BACKEND_INGEST_ENDPOINT", "/api/v1/internal/scrape/results"
    )
    backend_job_status_endpoint: str = os.getenv(
        "BACKEND_JOB_STATUS_ENDPOINT", "/api/v1/internal/scrape/job-status"
    )

    default_source: str = os.getenv("SCRAPER_DEFAULT_SOURCE", "hybrid")
    timeout_seconds: int = int(os.getenv("SCRAPER_TIMEOUT_SECONDS", "25"))
    headless: bool = os.getenv("SCRAPER_HEADLESS", "true").lower() == "true"
    pihps_lookback_days: int = int(os.getenv("PIHPS_LOOKBACK_DAYS", "30"))

    redis_host: str = os.getenv("REDIS_HOST", "127.0.0.1")
    redis_port: int = int(os.getenv("REDIS_PORT", "6379"))
    redis_db: int = int(os.getenv("REDIS_DB", "0"))
    redis_queue_key: str = os.getenv("REDIS_QUEUE_KEY", "scrape:jobs")


settings = Settings()
