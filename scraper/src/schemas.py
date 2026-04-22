from __future__ import annotations

from datetime import datetime
from typing import Any

from pydantic import BaseModel, Field


class ScrapeJobPayload(BaseModel):
    id: int = Field(..., description="Scrape job id from Laravel")
    source_name: str
    watchlist_id: int | None = None
    product_hint: str | None = None
    payload: dict[str, Any] | None = None


class ScrapeItem(BaseModel):
    listing_title: str
    listing_price_text: str
    listing_unit_text: str | None = None
    listing_url: str | None = None
    supplier_name: str | None = None
    supplier_external_id: str | None = None
    raw_payload: dict[str, Any] | None = None
    scraped_at: datetime = Field(default_factory=datetime.utcnow)


class IngestRequest(BaseModel):
    scrape_job_id: int
    source_name: str | None = None
    items: list[ScrapeItem]


class DispatchResponse(BaseModel):
    data: list[dict[str, Any]] = Field(default_factory=list)
