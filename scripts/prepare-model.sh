#!/usr/bin/env bash
set -euo pipefail
stage="$(cd "$(dirname "$0")/.." && pwd)"
destination="$HOME/projetos/system-knowledge-extractor"
test -f "$destination/.ske-managed"
cp -a "$stage/." "$destination/"
cd "$destination"
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait --wait-timeout 180 > /tmp/ske-model-build.log 2>&1 || { tail -n 80 /tmp/ske-model-build.log; exit 1; }
bash scripts/download-model.sh
docker compose restart worker
echo 'LOCAL_MODEL_READY'
