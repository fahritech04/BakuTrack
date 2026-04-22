from __future__ import annotations

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

from src.engine import ScrapeEngine
from src.schemas import ScrapeJobPayload

app = FastAPI(title="BakuTrack Scraper", version="0.1.0")
engine = ScrapeEngine()


class DispatchCycleRequest(BaseModel):
    source_name: str | None = None
    tenant_id: int | None = None


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/scrape/job")
async def scrape_single_job(job: ScrapeJobPayload) -> dict[str, bool]:
    success = await engine.run_job(job)

    if not success:
        raise HTTPException(status_code=500, detail="failed to process scrape job")

    return {"success": True}


@app.post("/scrape/dispatch-cycle")
async def scrape_dispatch_cycle(payload: DispatchCycleRequest) -> dict[str, int]:
    stats = await engine.run_dispatch_cycle(
        source_name=payload.source_name,
        tenant_id=payload.tenant_id,
    )
    return {
        "requested": int(stats["requested"]),
        "success": int(stats["success"]),
        "failed": int(stats["failed"]),
    }
