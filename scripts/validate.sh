#!/usr/bin/env bash
set -euo pipefail
stage="$(cd "$(dirname "$0")/.." && pwd)"
destination="$HOME/projetos/system-knowledge-extractor"
test -f "$destination/.ske-managed"
cp -a "$stage/." "$destination/"
cd "$destination"
# Keep Windows staging permissions from making every source file executable in Git.
find app frontend worker docs tests docker -type f -exec chmod 644 {} +
chmod 644 compose*.yml README.md .env.example .gitignore .dockerignore
chmod 755 scripts/*.sh
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait --wait-timeout 180 > /tmp/ske-validation-build.log 2>&1 || { tail -n 100 /tmp/ske-validation-build.log; exit 1; }
docker compose exec -T web php bin/migrate.php
docker compose restart worker scheduler
docker compose exec -T worker python -X pycache_prefix=/tmp/pycache -m compileall -q /worker
python3 -m unittest discover -s tests -p 'test_*.py' -v
python3 tests/foundation.py
python3 tests/media.py
python3 tests/knowledge.py
echo 'VALIDATION_PASSED'
