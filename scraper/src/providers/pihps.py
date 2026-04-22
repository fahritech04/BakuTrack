from __future__ import annotations

import re
from datetime import datetime, timedelta, timezone
from typing import Any

import httpx

from src.config import settings
from src.providers.base import BaseProvider
from src.providers.common import keyword_tokens
from src.schemas import ScrapeItem, ScrapeJobPayload

PIHPS_BASE_URL = "https://www.bi.go.id"
PIHPS_COMMODITY_URL = f"{PIHPS_BASE_URL}/hargapangan/WebSite/TabelHarga/GetRefCommodityAndCategory"
PIHPS_GRID_URL = f"{PIHPS_BASE_URL}/hargapangan/WebSite/TabelHarga/GetGridDataDaerah"
PIHPS_TABLE_URL = f"{PIHPS_BASE_URL}/hargapangan/TabelHarga/PasarTradisionalDaerah"
DATE_KEY_REGEX = re.compile(r"^\d{2}/\d{2}/\d{4}$")


class PihpsProvider(BaseProvider):
    source_name = "pihps"

    async def scrape(self, job: ScrapeJobPayload) -> list[ScrapeItem]:
        keyword = (job.product_hint or "").strip()
        if not keyword:
            return []

        async with httpx.AsyncClient(timeout=30.0) as client:
            commodity_response = await client.get(PIHPS_COMMODITY_URL)
            commodity_response.raise_for_status()
            commodity_rows = commodity_response.json().get("data", [])

            selected = self._find_best_commodity(keyword, commodity_rows)
            if selected is None:
                return []

            today = datetime.now(timezone.utc).date()
            start_date = today - timedelta(days=max(7, settings.pihps_lookback_days))
            grid_response = await client.get(
                PIHPS_GRID_URL,
                params={
                    "price_type_id": 1,
                    "comcat_id": selected["id"],
                    "province_id": "",
                    "regency_id": "",
                    "market_id": "",
                    "tipe_laporan": 1,
                    "start_date": start_date.isoformat(),
                    "end_date": today.isoformat(),
                },
            )
            grid_response.raise_for_status()
            grid_rows = grid_response.json().get("data", [])

        latest_value = self._extract_latest_price_value(grid_rows)
        if latest_value is None:
            return []

        value, observed_date = latest_value
        price_text = f"Rp{value:,.0f}".replace(",", ".")

        return [
            ScrapeItem(
                listing_title=selected["name"],
                listing_price_text=price_text,
                listing_unit_text="1 kg",
                listing_url=PIHPS_TABLE_URL,
                supplier_name="PIHPS Nasional (Bank Indonesia)",
                supplier_external_id="pihps_bi",
                raw_payload={
                    "source": "pihps",
                    "commodity_id": selected["id"],
                    "commodity_name": selected["name"],
                    "observed_date": observed_date,
                    "price_idr_per_kg": value,
                },
                scraped_at=datetime.utcnow(),
            )
        ]

    def _find_best_commodity(
        self,
        keyword: str,
        rows: list[dict[str, Any]],
    ) -> dict[str, Any] | None:
        candidates = [row for row in rows if isinstance(row.get("id"), str) and row["id"].startswith("com_")]
        if not candidates:
            return None

        keyword_norm = _normalize(keyword)
        keyword_token_list = keyword_tokens(keyword_norm)
        if not keyword_token_list:
            return None

        # Exact contains strategy first.
        for candidate in candidates:
            name = str(candidate.get("name", ""))
            name_norm = _normalize(name)
            if keyword_norm in name_norm:
                return candidate

        best_score = -1.0
        best_candidate: dict[str, Any] | None = None

        for candidate in candidates:
            name = str(candidate.get("name", ""))
            name_norm = _normalize(name)
            name_tokens = set(keyword_tokens(name_norm))

            if not name_tokens:
                continue

            matched = sum(1 for token in keyword_token_list if token in name_tokens)
            coverage = matched / max(1, len(keyword_token_list))

            # Keep multi-token query matching strict enough to reduce drift.
            if len(keyword_token_list) <= 2 and matched < len(keyword_token_list):
                continue
            if len(keyword_token_list) >= 3 and matched < 2:
                continue

            if coverage > best_score:
                best_score = coverage
                best_candidate = candidate

        return best_candidate if best_score >= 0.6 else None

    def _extract_latest_price_value(
        self,
        rows: list[dict[str, Any]],
    ) -> tuple[float, str] | None:
        if not rows:
            return None

        for row in rows:
            if not isinstance(row, dict):
                continue

            dated_values: list[tuple[datetime, float, str]] = []
            for key, raw_value in row.items():
                if not isinstance(key, str) or DATE_KEY_REGEX.match(key) is None:
                    continue

                parsed_value = _parse_numeric_price(raw_value)
                if parsed_value is None:
                    continue

                try:
                    parsed_date = datetime.strptime(key, "%d/%m/%Y")
                except ValueError:
                    continue

                dated_values.append((parsed_date, parsed_value, key))

            if dated_values:
                dated_values.sort(key=lambda item: item[0], reverse=True)
                _, value, date_text = dated_values[0]
                return value, date_text

        return None


def _normalize(value: str) -> str:
    normalized = re.sub(r"[^a-z0-9\s]", " ", value.lower())
    normalized = re.sub(r"\s+", " ", normalized).strip()
    return normalized


def _parse_numeric_price(raw_value: Any) -> float | None:
    if raw_value is None:
        return None

    text = str(raw_value).strip()
    if text == "-" or text == "":
        return None

    digits_only = re.sub(r"[^0-9]", "", text)
    if digits_only == "":
        return None

    value = float(digits_only)
    if value <= 0:
        return None

    return value
