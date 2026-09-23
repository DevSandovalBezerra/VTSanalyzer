#!/usr/bin/env bash
set -euo pipefail
stage="$(cd "$(dirname "$0")/.." && pwd)"
destination="$HOME/projetos/system-knowledge-extractor"
test -f "$destination/.ske-managed"
cp -a "$stage/." "$destination/"
cd "$destination"
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait --wait-timeout 180 > /tmp/ske-build.log 2>&1 || { tail -n 80 /tmp/ske-build.log; exit 1; }
docker compose exec -T web php bin/migrate.php
bash scripts/verify-foundation.sh
if [[ ! -f .local-access.txt ]]; then
  python3 - <<'PY'
import secrets, subprocess
from pathlib import Path
password=secrets.token_urlsafe(20)
subprocess.run(['docker','compose','exec','-T','web','php','bin/create-user.php','admin@local.test'],input=password.encode(),check=True)
path=Path('.local-access.txt')
path.write_text('URL: http://localhost:8095\nE-mail: admin@local.test\nSenha: '+password+'\n')
path.chmod(0o600)
PY
fi
git add .
if ! git diff --cached --quiet; then
  git -c user.name='System Knowledge Bootstrap' -c user.email='bootstrap@local.invalid' commit -m 'feat: establish verified WSL Docker foundation'
fi
docker compose ps
echo 'FOUNDATION_GATE_PASSED'
