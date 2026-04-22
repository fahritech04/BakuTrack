from __future__ import annotations

import re
from datetime import datetime
from statistics import median
from urllib.parse import quote

import httpx
from bs4 import BeautifulSoup

from src.providers.base import BaseProvider
from src.providers.common import (
    clean_text,
    clean_title,
    contains_phrase,
    extract_first_price_text,
    is_relevant_title_strict,
    keyword_tokens,
    normalize_for_match,
)
from src.schemas import ScrapeItem, ScrapeJobPayload

TOKOPEDIA_FIND_BASE_URL = "https://www.tokopedia.com/find"
TOKOPEDIA_HEADERS = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) "
        "Chrome/124.0.0.0 Safari/537.36"
    ),
    "Accept-Language": "id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7",
}
HREF_ALLOWED_PREFIX = "https://www.tokopedia.com/"
TOKOPEDIA_BLOCK_PHRASES = {
    "mainan",
    "boneka",
    "toy",
    "training cup",
    "food processor",
    "kulkas",
    "showcase",
    "vending",
    "sikat",
}


class TokopediaProvider(BaseProvider):
    source_name = "tokopedia"

    async def scrape(self, job: ScrapeJobPayload) -> list[ScrapeItem]:
        keyword = (job.product_hint or "").strip()
        if not keyword:
            return []

        queries = self._build_query_variants(keyword)
        seen: set[tuple[str, str]] = set()
        items: list[ScrapeItem] = []

        async with httpx.AsyncClient(timeout=30.0, headers=TOKOPEDIA_HEADERS, follow_redirects=True) as client:
            for query in queries:
                search_url = self._build_search_url(query)

                try:
                    response = await client.get(search_url)
                except Exception:
                    continue

                if response.status_code >= 400:
                    continue

                extracted = self._extract_items(
                    html=response.text,
                    search_url=search_url,
                    original_keyword=keyword,
                )

                for extracted_item in extracted:
                    dedupe_key = (
                        extracted_item.listing_title.lower(),
                        extracted_item.listing_price_text,
                    )
                    if dedupe_key in seen:
                        continue

                    seen.add(dedupe_key)
                    items.append(extracted_item)

                    if len(items) >= 6:
                        return items

        return items

    def _extract_items(
        self,
        html: str,
        search_url: str,
        original_keyword: str,
    ) -> list[ScrapeItem]:
        soup = BeautifulSoup(html, "html.parser")
        anchors = soup.select("a[href]")
        candidates: list[tuple[ScrapeItem, float]] = []

        for anchor in anchors:
            href = str(anchor.get("href") or "").strip()
            if not href.startswith(HREF_ALLOWED_PREFIX):
                continue
            if "/find/" in href or "/help/" in href or "/blog/" in href:
                continue

            raw_text = clean_text(anchor.get_text(" ", strip=True))
            if len(raw_text) < 12:
                continue

            price_text = extract_first_price_text(raw_text)
            if not price_text:
                continue

            title_part = raw_text.split(price_text, 1)[0].strip()
            title = clean_title(title_part)
            if len(title) < 6:
                continue

            if not is_relevant_title_strict(title, original_keyword):
                continue
            if not self._passes_noise_guard(title, original_keyword):
                continue

            supplier_name = self._extract_seller_from_url(href) or "Tokopedia"

            item = ScrapeItem(
                listing_title=title,
                listing_price_text=price_text,
                listing_unit_text=None,
                listing_url=href,
                supplier_name=supplier_name,
                raw_payload={
                    "source": self.source_name,
                    "search_url": search_url,
                    "raw_text": raw_text[:600],
                },
                scraped_at=datetime.utcnow(),
            )
            parsed_price = self._parse_price_value(price_text)
            if parsed_price is None:
                continue

            candidates.append((item, parsed_price))

            if len(candidates) >= 14:
                break

        filtered = self._filter_outlier_candidates(candidates)
        return [item for item, _ in filtered]

    def _build_search_url(self, keyword: str) -> str:
        cleaned = keyword.strip().lower()
        cleaned = re.sub(r"[^a-z0-9\s-]", " ", cleaned)
        cleaned = re.sub(r"\s+", "-", cleaned).strip("-")
        return f"{TOKOPEDIA_FIND_BASE_URL}/{quote(cleaned)}"

    def _build_query_variants(self, keyword: str) -> list[str]:
        variants: list[str] = [keyword]
        normalized = normalize_for_match(keyword)
        tokens = keyword_tokens(normalized)

        if len(tokens) == 1:
            variants.append(f"{tokens[0]} grosir")
            variants.append(f"{tokens[0]} supplier")
            variants.append(f"{tokens[0]} bahan baku")
        elif len(tokens) >= 2:
            variants.append(f"{' '.join(tokens)} grosir")

        if len(tokens) >= 2:
            variants.append(" ".join(tokens))

        if len(tokens) >= 4:
            variants.append(" ".join(tokens[:4]))
            variants.append(" ".join(tokens[-4:]))

        if len(tokens) >= 3:
            variants.append(" ".join(tokens[:3]))
            variants.append(" ".join(tokens[-3:]))

        # Keep order but remove duplicates.
        unique: list[str] = []
        seen: set[str] = set()
        for variant in variants:
            normalized_variant = normalize_for_match(variant)
            if normalized_variant in seen:
                continue
            seen.add(normalized_variant)
            unique.append(variant)

        return unique

    def _passes_noise_guard(self, title: str, keyword: str) -> bool:
        normalized_title = normalize_for_match(title)
        keyword_token_list = keyword_tokens(keyword)

        if any(contains_phrase(normalized_title, phrase) for phrase in TOKOPEDIA_BLOCK_PHRASES):
            return False

        # Additional lexical anchor: avoid unrelated products that happen
        # to contain a single weak token.
        if keyword_token_list:
            longest_token = max(keyword_token_list, key=len)
            if contains_phrase(normalized_title, longest_token) is False:
                return False

        return True

    def _parse_price_value(self, price_text: str) -> float | None:
        digits = re.sub(r"[^0-9]", "", price_text)
        if not digits:
            return None

        value = float(digits)
        return value if value > 0 else None

    def _filter_outlier_candidates(self, candidates: list[tuple[ScrapeItem, float]]) -> list[tuple[ScrapeItem, float]]:
        if len(candidates) < 4:
            return candidates

        baseline = median(price for _, price in candidates)
        if baseline <= 0:
            return candidates

        filtered = [
            (item, price)
            for item, price in candidates
            if price >= baseline * 0.35 and price <= baseline * 3.0
        ]

        return filtered if filtered else candidates

    def _extract_seller_from_url(self, href: str) -> str | None:
        # Tokopedia product URL shape: https://www.tokopedia.com/<seller>/<slug>
        path = href.replace(HREF_ALLOWED_PREFIX, "", 1).strip("/")
        if not path:
            return None

        seller_slug = path.split("/", 1)[0].strip()
        if not seller_slug or seller_slug in {"p", "search"}:
            return None

        seller_name = seller_slug.replace("-", " ").replace("_", " ").strip()
        seller_name = re.sub(r"\s+", " ", seller_name)
        return seller_name.title()
