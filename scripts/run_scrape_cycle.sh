#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR/scraper"
source .venv/bin/activate
python -m src.runner --source "${1:-hybrid}"
