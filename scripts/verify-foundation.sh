#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
dc=(docker compose -f compose.yml -f compose.dev.yml)
"${dc[@]}" config --quiet
python3 tests/foundation.py
"${dc[@]}" exec -T --user www-data web php -r 'file_put_contents("/data/foundation-persistence.txt", "persistent");'
"${dc[@]}" exec -T web php -r 'require "src/bootstrap.php"; query("INSERT INTO audit_events(action) VALUES (?)", ["foundation.persistence"]); queue()->set("foundation:persistence", "ok");'
"${dc[@]}" up -d --force-recreate --wait --wait-timeout 180
"${dc[@]}" exec -T web php -r 'if(file_get_contents("/data/foundation-persistence.txt")!=="persistent")exit(1);'
"${dc[@]}" exec -T web php -r 'require "src/bootstrap.php"; if(!query("SELECT 1 FROM audit_events WHERE action=?", ["foundation.persistence"])->fetchColumn() || queue()->get("foundation:persistence")!=="ok")exit(1);'
"${dc[@]}" exec -T worker python -c 'from pathlib import Path; assert Path("/data/foundation-persistence.txt").read_text()=="persistent"; assert Path("/target/README.md").is_file()'
"${dc[@]}" exec -T worker python -c 'from pathlib import Path
try:
    Path("/target/must-not-write").write_text("denied")
except OSError:
    print("PASS: target write denied")
else:
    raise SystemExit("ERROR: target writable")'
printf '<?php echo "bind-php-ok";' > app/bind-check.php
printf 'VALUE = "bind-python-ok"\n' > worker/bind_check.py
"${dc[@]}" exec -T web php /app/bind-check.php
"${dc[@]}" exec -T worker python -c 'import bind_check; assert bind_check.VALUE=="bind-python-ok"'
python3 -c 'from pathlib import Path; Path("app/bind-check.php").unlink(); Path("worker/bind_check.py").unlink()'
echo 'PASS: persistence after recreation and PHP/Python bind mounts'
