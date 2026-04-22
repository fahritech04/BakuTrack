from __future__ import annotations

import re
from typing import Any

from playwright.async_api import Page

PRICE_REGEX = re.compile(r"Rp\s*[\d\.,]+", re.IGNORECASE)
WHITESPACE_REGEX = re.compile(r"\s+")
LEADING_DISCOUNT_REGEX = re.compile(r"^\d+\s*%\s*", re.IGNORECASE)
LEADING_UNIT_REGEX = re.compile(r"^\d+\s*unit\s*", re.IGNORECASE)
LEADING_PACK_REGEX = re.compile(r"^\d+\s*(box|dus|karton|pack)\s*", re.IGNORECASE)
LEADING_NUMBER_REGEX = re.compile(r"^\d+\s*", re.IGNORECASE)
NON_WORD_REGEX = re.compile(r"[^a-z0-9\s]", re.IGNORECASE)
STOPWORD_TOKENS = {
    "dan",
    "isi",
    "pcs",
    "pc",
    "kg",
    "g",
    "gr",
    "gram",
    "ml",
    "l",
    "pack",
    "box",
    "dus",
    "karton",
}
GLOBAL_BLOCK_PHRASES = {
    "mainan",
    "boneka",
    "karpet",
    "alas kaki",
    "toy",
    "mesin",
    "kulkas",
    "showcase",
    "vending",
    "food processor",
    "cup sealer",
    "dispenser",
    "training cup",
    "botol minum",
    "sikat",
}
CONTEXT_BLOCK_TOKENS = {
    "holder",
    "pegangan",
    "wadah",
    "tempat",
    "rak",
    "organizer",
    "dispenser",
    "aspirator",
    "hidung",
    "pembersih",
    "bayi",
    "baby",
    "anak",
    "training",
    "sparepart",
    "aksesoris",
    "accessories",
}


def clean_text(value: str) -> str:
    return WHITESPACE_REGEX.sub(" ", value).strip()


def clean_title(value: str) -> str:
    normalized = clean_text(value)
    normalized = LEADING_DISCOUNT_REGEX.sub("", normalized)
    normalized = LEADING_UNIT_REGEX.sub("", normalized)
    normalized = LEADING_PACK_REGEX.sub("", normalized)
    normalized = re.sub(r"^\d+\s*(unit|box|dus|karton|pack)(?=[A-Za-z])", "", normalized, flags=re.IGNORECASE)
    normalized = LEADING_NUMBER_REGEX.sub("", normalized)
    normalized = normalized.strip("-:| ")

    if len(normalized) > 140:
        normalized = normalized[:137].rstrip() + "..."

    return normalized


def extract_first_price_text(value: str) -> str | None:
    match = PRICE_REGEX.search(value)
    return match.group(0) if match else None


def keyword_tokens(keyword: str | None) -> list[str]:
    if not keyword:
        return []

    normalized = NON_WORD_REGEX.sub(" ", keyword.lower())
    tokens = [token for token in normalized.split() if len(token) >= 3 and token not in STOPWORD_TOKENS]
    return tokens


def is_relevant_title(title: str, keyword: str | None) -> bool:
    return is_relevant_title_strict(title, keyword)


def is_relevant_title_strict(title: str, keyword: str | None) -> bool:
    tokens = keyword_tokens(keyword)
    if not tokens:
        return True

    normalized_title = normalize_for_match(title)
    title_tokens = set(keyword_tokens(normalized_title))
    matched = sum(1 for token in tokens if token in title_tokens)

    token_count = len(tokens)
    if token_count == 1:
        if matched < 1:
            return False
        if not is_single_token_prominent(normalized_title, tokens[0]):
            return False
    if token_count == 2 and matched < 2:
        return False
    if token_count >= 3:
        required = max(2, int(token_count * 0.6 + 0.999))  # ceil(token_count * 0.6)
        if matched < required:
            return False

    # Keep a strong anchor so generic search results do not drift.
    longest_token = max(tokens, key=len)
    if contains_phrase(normalized_title, longest_token) is False:
        return False

    if not is_semantically_relevant_title(normalized_title):
        return False
    if has_context_conflict(tokens, title_tokens):
        return False

    return True


def normalize_for_match(value: str) -> str:
    normalized = NON_WORD_REGEX.sub(" ", value.lower())
    normalized = WHITESPACE_REGEX.sub(" ", normalized).strip()
    return normalized


def is_semantically_relevant_title(normalized_title: str) -> bool:
    return not has_any_phrase(normalized_title, GLOBAL_BLOCK_PHRASES)


def has_any_phrase(normalized_text: str, phrases: set[str]) -> bool:
    return any(contains_phrase(normalized_text, phrase) for phrase in phrases)


def has_context_conflict(query_tokens: list[str], title_tokens: set[str]) -> bool:
    blocked_in_title = {token for token in CONTEXT_BLOCK_TOKENS if token in title_tokens}
    if not blocked_in_title:
        return False

    # If watchlist explicitly asks for one of these terms, allow it.
    requested = set(query_tokens)
    return blocked_in_title.isdisjoint(requested)


def is_single_token_prominent(normalized_title: str, token: str) -> bool:
    if not token:
        return True

    position = normalized_title.find(token)
    if position < 0:
        return False

    # For single-word watchlist, require keyword to appear early enough
    # so trailing mentions on unrelated products are filtered out.
    return position <= max(0, int(len(normalized_title) * 0.55))


def contains_phrase(normalized_text: str, phrase: str) -> bool:
    normalized_phrase = normalize_for_match(phrase)
    if not normalized_phrase:
        return False
    if " " in normalized_phrase:
        return normalized_phrase in normalized_text

    return re.search(rf"\b{re.escape(normalized_phrase)}\b", normalized_text) is not None


def infer_unit_text(title: str) -> str | None:
    match = re.search(r"(\d+(?:[\.,]\d+)?)\s*(kg|g|gr|l|ml|pcs|pc|butir)", title, re.IGNORECASE)
    if not match:
        return None

    return f"{match.group(1)} {match.group(2).lower()}"


async def extract_candidates_from_page(page: Page, href_hint: str) -> list[dict[str, Any]]:
    raw: list[dict[str, Any]] = await page.evaluate(
        r"""
        (hrefHint) => {
          const rows = [];
          const priceRe = /Rp\s*[\d\.,]+/i;
          const anchors = Array.from(document.querySelectorAll('a[href]'));

          for (const anchor of anchors) {
            const rawHref = anchor.getAttribute('href') || '';
            const href = anchor.href || rawHref;

            if (hrefHint && !rawHref.includes(hrefHint) && !href.includes(hrefHint)) {
              continue;
            }

            const text = (anchor.innerText || anchor.textContent || '').replace(/\s+/g, ' ').trim();
            if (!text || text.length < 8) {
              continue;
            }

            const priceMatch = text.match(priceRe);
            if (!priceMatch) {
              continue;
            }

            let title = text.split(priceRe)[0].trim();
            if (!title) {
              title = text.slice(0, 100).trim();
            }

            rows.push({
              title,
              price_text: priceMatch[0],
              href,
              raw_text: text,
            });

            if (rows.length >= 20) {
              break;
            }
          }

          return rows;
        }
        """,
        href_hint,
    )

    return raw
