from __future__ import annotations

from datetime import datetime
from urllib.parse import quote

from playwright.async_api import async_playwright

from src.config import settings
from src.providers.base import BaseProvider
from src.providers.common import (
    clean_title,
    contains_phrase,
    is_relevant_title_strict,
    normalize_for_match,
)
from src.schemas import ScrapeItem, ScrapeJobPayload

RALALI_BLOCK_PHRASES = {
    "mainan",
    "boneka",
    "toy",
    "karpet",
    "alas kaki",
    "kulkas",
    "showcase",
    "food processor",
}


class RalaliProvider(BaseProvider):
    source_name = "ralali"

    async def scrape(self, job: ScrapeJobPayload) -> list[ScrapeItem]:
        keyword = (job.product_hint or "").strip()
        if not keyword:
            return []

        search_url = f"https://www.ralali.com/search/{quote(keyword.replace(' ', '-'))}"
        captured_payloads: list[dict] = []

        async with async_playwright() as playwright:
            browser = await playwright.chromium.launch(headless=settings.headless)
            page = await browser.new_page()

            async def on_response(response) -> None:
                if "apigw.ralali.com/search/v3/items" not in response.url:
                    return
                if response.status != 200:
                    return

                try:
                    payload = await response.json()
                except Exception:
                    return

                if isinstance(payload, dict):
                    captured_payloads.append(payload)

            page.on("response", on_response)
            await page.goto(search_url, timeout=settings.timeout_seconds * 1000, wait_until="domcontentloaded")
            await page.wait_for_timeout(5000)
            await browser.close()

        if not captured_payloads:
            return []

        items: list[ScrapeItem] = []
        seen: set[tuple[str, str]] = set()

        for payload in reversed(captured_payloads):
            raw_items = payload.get("data", {}).get("items", [])
            if not isinstance(raw_items, list):
                continue

            for raw_item in raw_items:
                if not isinstance(raw_item, dict):
                    continue

                title = clean_title(str(raw_item.get("name") or ""))
                if not title:
                    continue
                if not is_relevant_title_strict(title, keyword):
                    continue
                if not self._passes_noise_guard(title):
                    continue

                raw_price = raw_item.get("price")
                if not isinstance(raw_price, (int, float)) or float(raw_price) <= 0:
                    continue

                price_text = f"Rp{float(raw_price):,.0f}".replace(",", ".")
                dedupe_key = (title.lower(), price_text)
                if dedupe_key in seen:
                    continue
                seen.add(dedupe_key)

                categories = []
                for category in raw_item.get("categories", []) or []:
                    name = str(category.get("name") or "").strip()
                    if name:
                        categories.append(name)

                items.append(
                    ScrapeItem(
                        listing_title=title,
                        listing_price_text=price_text,
                        listing_unit_text=None,
                        listing_url=None,
                        supplier_name=str(raw_item.get("vendor_name") or "Ralali"),
                        raw_payload={
                            "source": "ralali",
                            "search_url": search_url,
                            "categories": categories,
                            "vendor_id": raw_item.get("vendor_id"),
                            "alias": raw_item.get("alias"),
                        },
                        scraped_at=datetime.utcnow(),
                    )
                )

                if len(items) >= 5:
                    return items

        return items

    def _passes_noise_guard(self, title: str) -> bool:
        normalized_title = normalize_for_match(title)
        return not any(contains_phrase(normalized_title, phrase) for phrase in RALALI_BLOCK_PHRASES)
