#!/usr/bin/env python3
"""Bridge native Windows Codex hook JSON into the WSL ai-memory client.

Only declared Windows paths are translated; no command or event text executes.
Install outside the checkout so global hooks survive repository relocation.
"""
import json
from pathlib import Path
import re
import subprocess
import sys


def linux_path(value):
    if not isinstance(value, str):
        return value
    if re.match(r'^[A-Za-z]:[\\/]', value):
        return '/mnt/' + value[0].lower() + '/' + value[3:].replace('\\', '/')
    normalized = value.replace('\\', '/')
    for prefix in ('//wsl.localhost/Ubuntu-24.04/', '//wsl$/Ubuntu-24.04/'):
        if normalized.lower().startswith(prefix.lower()):
            return '/' + normalized[len(prefix):]
    return value


def translate(value):
    if isinstance(value, dict):
        return {key: linux_path(item) if key in {'cwd', 'file_path', 'path', 'workdir', 'working_directory'} else translate(item)
                for key, item in value.items()}
    if isinstance(value, list):
        return [translate(item) for item in value]
    return value


def main():
    events = {'session-start', 'user-prompt-submit', 'pre-tool-use', 'post-tool-use', 'pre-compact', 'stop', 'session-end'}
    if len(sys.argv) != 2 or sys.argv[1] not in events:
        return 2
    raw = sys.stdin.buffer.read(2 * 1024 * 1024 + 1)
    if len(raw) > 2 * 1024 * 1024:
        return 0
    try:
        event = translate(json.loads(raw.decode('utf-8-sig')))
    except (ValueError, UnicodeError):
        return 0
    if not isinstance(event, dict) or not isinstance(event.get('cwd'), str) or not Path(event['cwd']).is_dir():
        return 0
    # Allowlist admits only checkouts with an explicit .ai-memory.toml marker.
    command = [str(Path.home() / '.local/bin/ai-memory'), 'hook', '--agent', 'codex',
               '--event', sys.argv[1], '--server-url', 'http://127.0.0.1:49374',
               '--project-strategy', 'repo-root', '--capture-mode', 'allowlist']
    try:
        return subprocess.run(command, input=json.dumps(event, ensure_ascii=False).encode('utf-8'),
                              cwd=event['cwd'], timeout=12).returncode
    except (OSError, subprocess.TimeoutExpired):
        return 0


if __name__ == '__main__':
    sys.exit(main())
