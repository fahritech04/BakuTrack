from __future__ import annotations

from typing import Any

import httpx
import redis

from src.config import settings
from src.providers.base import BaseProvider
from src.providers.gudangada import GudangAdaProvider
from src.providers.pihps import PihpsProvider
from src.providers.ralali import RalaliProvider
from src.providers.tokopedia import TokopediaProvider
from src.schemas import DispatchResponse, IngestRequest, ScrapeJobPayload


class ScrapeEngine:
    def __init__(self) -> None:
        self.providers: dict[str, BaseProvider] = {
            PihpsProvider.source_name: PihpsProvider(),
            TokopediaProvider.source_name: TokopediaProvider(),
            RalaliProvider.source_name: RalaliProvider(),
            GudangAdaProvider.source_name: GudangAdaProvider(),
        }
        self.redis_client = redis.Redis(
            host=settings.redis_host,
            port=settings.redis_port,
            db=settings.redis_db,
            decode_responses=True,
        )

    async def run_dispatch_cycle(
        self,
        source_name: str | None = None,
        tenant_id: int | None = None,
    ) -> dict[str, Any]:
        jobs = await self.request_dispatch_jobs(source_name=source_name, tenant_id=tenant_id)

        success_count = 0
        for job_data in jobs:
            job_payload = ScrapeJobPayload(
                id=job_data["id"],
                source_name=job_data.get("source_name", settings.default_source),
                watchlist_id=job_data.get("watchlist_id"),
                product_hint=(job_data.get("payload") or {}).get("product_hint"),
                payload=job_data.get("payload"),
            )
            result_ok = await self.run_job(job_payload)
            success_count += 1 if result_ok else 0

        return {
            "requested": len(jobs),
            "success": success_count,
            "failed": len(jobs) - success_count,
        }

    async def run_job(self, job: ScrapeJobPayload) -> bool:
        provider_chain = self.resolve_provider_chain(job.source_name)
        await self.report_job_status(job.id, status="running")

        if not provider_chain:
            await self.report_job_status(job.id, status="failed", error_message="provider_not_found")
            return False

        items: list = []
        resolved_source: str | None = None
        last_error: str | None = None

        for provider_name in provider_chain:
            provider = self.providers.get(provider_name)
            if provider is None:
                continue

            try:
                provider_items = await provider.scrape(job)
            except Exception as exc:
                last_error = f"{provider_name}:scrape_exception:{type(exc).__name__}"
                continue

            if provider_items:
                items = provider_items
                resolved_source = provider_name
                break

        if not items or resolved_source is None:
            await self.report_job_status(job.id, status="failed", error_message="no_relevant_items")
            return False

        ingest_payload = IngestRequest(scrape_job_id=job.id, source_name=resolved_source, items=items)

        async with httpx.AsyncClient(timeout=settings.timeout_seconds) as client:
            response = await client.post(
                f"{settings.backend_base_url}{settings.backend_ingest_endpoint}",
                headers={"X-Internal-Key": settings.backend_internal_key},
                json=ingest_payload.model_dump(mode="json"),
            )
            if response.status_code < 300:
                return True

        await self.report_job_status(
            job.id,
            status="failed",
            error_message=last_error or f"ingest_http_{response.status_code}",
        )
        return False

    def resolve_provider_chain(self, source_name: str) -> list[str]:
        requested = (source_name or settings.default_source or "").strip().lower()
        if requested in {"hybrid", "auto", "default"}:
            return ["pihps", "tokopedia", "ralali", "gudangada"]
        if requested == "pihps":
            return ["pihps", "tokopedia", "ralali"]
        if requested == "tokopedia":
            return ["tokopedia", "ralali"]
        if requested in self.providers:
            return [requested]

        fallback = (settings.default_source or "hybrid").strip().lower()
        if fallback in {"hybrid", "auto", "default"}:
            return ["pihps", "tokopedia", "ralali", "gudangada"]
        if fallback in self.providers:
            return [fallback]

        return []

    async def request_dispatch_jobs(
        self,
        source_name: str | None = None,
        tenant_id: int | None = None,
    ) -> list[dict[str, Any]]:
        async with httpx.AsyncClient(timeout=settings.timeout_seconds) as client:
            response = await client.post(
                f"{settings.backend_base_url}{settings.backend_dispatch_endpoint}",
                headers={"X-Internal-Key": settings.backend_internal_key},
                json={
                    "source_name": source_name or settings.default_source,
                    "tenant_id": tenant_id,
                },
            )

        response.raise_for_status()
        payload = DispatchResponse.model_validate(response.json())

        return payload.data

    def enqueue_job(self, job: dict[str, Any]) -> None:
        self.redis_client.rpush(settings.redis_queue_key, str(job))

    async def report_job_status(
        self,
        scrape_job_id: int,
        status: str,
        error_message: str | None = None,
    ) -> None:
        try:
            async with httpx.AsyncClient(timeout=settings.timeout_seconds) as client:
                await client.post(
                    f"{settings.backend_base_url}{settings.backend_job_status_endpoint}",
                    headers={"X-Internal-Key": settings.backend_internal_key},
                    json={
                        "scrape_job_id": scrape_job_id,
                        "status": status,
                        "error_message": error_message,
                    },
                )
        except Exception:
            return
