#!/usr/bin/env bash
# PreToolUse (Bash): refuses to pipe a pest run through tail/head/grep.
# Born 2026-09-30, and it is the SECOND time: the nightwatch run of 2026-09-29
# wrote it down ("antes de matar un proceso por colgado, mirar si ya terminó")
# and it happened again the next morning — a 33-second browser run looked hung
# for seven minutes because `| tail` holds every line until the process ends,
# and the run was killed for nothing.
#
# The fix is always the same: send the run to a file, then read the file.
set -uo pipefail

payload="$(cat)"

command="$(printf '%s' "$payload" | python3 -c '
import json,sys
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
print(d.get("tool_input", {}).get("command", "") or "")
' 2>/dev/null)"

[ -n "$command" ] || exit 0

case "$command" in
    *pest*|*"artisan test"*) ;;
    *) exit 0 ;;
esac

# A redirection to a file is the way out: the output is whole and readable.
case "$command" in
    *">"*) exit 0 ;;
esac

case "$command" in
    *"|"*tail*|*"|"*head*|*"|"*grep*) ;;
    *) exit 0 ;;
esac

cat >&2 <<'EOF'
BLOQUEADO: una corrida de pest canalizada por tail/head/grep.

El pipe RETIENE toda la salida hasta que el proceso termina: la corrida parece
colgada, y se termina matando un proceso que ya había terminado (pasó el
2026-09-30, y estaba anotado desde el 2026-09-29).

Hacelo así:
  ./vendor/bin/pest <archivo> > /tmp/pest.log 2>&1; echo "exit=$?"
y después leé /tmp/pest.log (Read, o grep SOBRE EL ARCHIVO).
EOF

exit 2
