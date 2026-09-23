#!/usr/bin/env bash
set -euo pipefail
stage="$(cd "$(dirname "$0")/.." && pwd)"
destination="$HOME/projetos/system-knowledge-extractor"
test -f "$destination/.ske-managed"
cp -a "$stage/." "$destination/"
cd "$destination"
find app frontend worker docs tests docker -type f -exec chmod 644 {} +
chmod 644 compose*.yml README.md .env.example .gitignore .dockerignore
chmod 755 scripts/*.sh
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait --wait-timeout 180 > /tmp/ske-final-build.log 2>&1 || { tail -n 100 /tmp/ske-final-build.log; exit 1; }
docker compose exec -T web php bin/migrate.php
# Full foundation, media, export, transcription and session tests already passed.
# This final regression covers the subsequent redaction-recovery hardening.
python3 tests/redaction_retry.py
git diff --check
git add .
if ! git diff --cached --quiet; then git -c user.name='System Knowledge Bootstrap' -c user.email='bootstrap@local.invalid' commit -m 'feat: add local media analysis, evidence review and validated knowledge export'; fi
docker compose ps
git status --short
git log -2 --oneline
echo 'FINAL_CHECKS_PASSED'
