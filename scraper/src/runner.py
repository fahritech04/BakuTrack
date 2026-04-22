from __future__ import annotations

import argparse
import asyncio
import json

from src.engine import ScrapeEngine


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Run BakuTrack scrape dispatch cycle")
    parser.add_argument("--source", required=False, help="Marketplace source name")
    parser.add_argument("--tenant-id", required=False, type=int, help="Specific tenant id")
    return parser.parse_args()


async def run() -> None:
    args = parse_args()
    engine = ScrapeEngine()
    stats = await engine.run_dispatch_cycle(
        source_name=args.source,
        tenant_id=args.tenant_id,
    )
    print(json.dumps(stats))


if __name__ == "__main__":
    asyncio.run(run())
