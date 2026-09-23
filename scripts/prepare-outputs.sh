#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
mkdir -p outputs
# Worker writes as uid 33; the WSL owner retains access through the group.
docker compose run --rm --no-deps --user root --entrypoint sh worker -c "chown 33:$(id -g) /outputs && chmod 2770 /outputs"
