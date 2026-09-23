#!/usr/bin/env bash
set -euo pipefail
stage="$(cd "$(dirname "$0")/.." && pwd)"
destination="$HOME/projetos/system-knowledge-extractor"
test -f "$destination/.ske-managed"
cp -a "$stage/tests/." "$destination/tests/"
cd "$destination"
python3 tests/transcription.py
