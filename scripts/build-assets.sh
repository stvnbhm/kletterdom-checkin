#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

command -v docker >/dev/null 2>&1 || { echo "ERROR: docker is required." >&2; exit 1; }
docker compose version >/dev/null 2>&1 || { echo "ERROR: Docker Compose plugin is required." >&2; exit 1; }

mkdir -p public/assets/css
docker compose --profile build run --rm node sh -c 'npm ci && npm run build'

if [[ ! -s public/assets/css/app.css ]]; then
    echo "ERROR: public/assets/css/app.css was not created." >&2
    exit 1
fi

echo "Assets built: public/assets/css/app.css (+ vendored JS refreshed)"
