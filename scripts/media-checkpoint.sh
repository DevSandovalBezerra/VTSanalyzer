#!/usr/bin/env bash
set -euo pipefail
stage="$(cd "$(dirname "$0")/.." && pwd)"
destination="$HOME/projetos/system-knowledge-extractor"
test -f "$destination/.ske-managed"
cp -a "$stage/." "$destination/"
cd "$destination"
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait --wait-timeout 180 > /tmp/ske-media-build.log 2>&1 || { tail -n 80 /tmp/ske-media-build.log; exit 1; }
docker compose exec -T web php bin/migrate.php
echo 'MEDIA_DEPLOYED'
