#!/usr/bin/env bash
set -euo pipefail
source_dir="$(cd "$(dirname "$0")/.." && pwd)"
destination="$HOME/projetos/system-knowledge-extractor"
if [[ -e "$destination" && ! -f "$destination/.ske-managed" ]]; then
  echo "Destino já existe e não foi criado por este instalador: $destination" >&2; exit 1
fi
mkdir -p "$destination"
cp -a "$source_dir/." "$destination/"
touch "$destination/.ske-managed"
cd "$destination"
if [[ ! -f .env ]]; then
  python3 - <<'PY'
from pathlib import Path
import secrets
content=Path('.env.example').read_text().replace('replace-with-random-secret',secrets.token_hex(32)).replace('replace-with-another-random-secret',secrets.token_hex(32))
Path('.env').write_text(content)
Path('.env').chmod(0o600)
PY
fi
if [[ ! -d .git ]]; then git init -b main; fi
echo "Projeto preparado em $destination"
docker compose -f compose.yml -f compose.dev.yml config --quiet
docker compose -f compose.yml -f compose.dev.yml up -d --build --wait --wait-timeout 180
docker compose exec -T web php bin/migrate.php
