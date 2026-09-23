#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
# Downloads model weights only. No user audio/video is sent.
docker compose exec -T worker python -c 'import os; from faster_whisper import WhisperModel; WhisperModel(os.getenv("TRANSCRIPTION_MODEL","small"), device="cpu", compute_type="int8", download_root="/home/app/.cache/whisper"); print("Modelo local disponível")'
