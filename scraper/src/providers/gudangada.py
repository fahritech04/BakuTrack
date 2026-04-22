from __future__ import annotations

from datetime import datetime

from playwright.async_api import async_playwright

from src.config import settings
from src.providers.base import BaseProvider
from src.providers.common import (
    clean_title,
    extract_candidates_from_page,
    is_relevant_title,
)
from src.schemas import ScrapeItem, ScrapeJobPayload


class GudangAdaProvider(BaseProvider):
    source_name = "gudangada"

    async def scrape(self, job: ScrapeJobPayload) -> list[ScrapeItem]:
        keyword = job.product_hint or "bahan baku"
        search_url = (
            f"https://www.gudangada.com/search?keyword={keyword.replace(' ', '%20')}"
        )

        async with async_playwright() as playwright:
            browser = await playwright.chromium.launch(headless=settings.headless)
            page = await browser.new_page()
            await page.goto(search_url, timeout=settings.timeout_seconds * 1000)
            await page.wait_for_timeout(3000)

            candidates = await extract_candidates_from_page(page, "product")
            await browser.close()

        items: list[ScrapeItem] = []

        for candidate in candidates:
            title = clean_title(candidate.get("title", ""))
            price_text = candidate.get("price_text", "")

            if not title or not price_text or not is_relevant_title(title, keyword):
                continue

            items.append(
                ScrapeItem(
                    listing_title=title,
                    listing_price_text=price_text,
                    listing_unit_text=None,
                    listing_url=candidate.get("href"),
                    supplier_name="GudangAda",
                    raw_payload={
                        "source": "gudangada",
                        "search_url": search_url,
                        "raw_text": candidate.get("raw_text"),
                    },
                    scraped_at=datetime.utcnow(),
                )
            )

            if len(items) >= 5:
                break

        return items
