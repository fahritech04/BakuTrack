from __future__ import annotations

from abc import ABC, abstractmethod

from src.schemas import ScrapeItem, ScrapeJobPayload


class BaseProvider(ABC):
    source_name: str

    @abstractmethod
    async def scrape(self, job: ScrapeJobPayload) -> list[ScrapeItem]:
        raise NotImplementedError
