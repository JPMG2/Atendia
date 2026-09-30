#!/usr/bin/env bash
# PreToolUse (Write): refuses to CREATE over a file that already exists with
# content. Born 2026-09-30: `lang/es/notifications.php` (the toast copy of the
# whole app) was overwritten by a Write that assumed the name was free — 44
# lines gone, caught only because git happened to be clean.
#
# Write is for NEW files. Changing an existing one is Edit, which forces
# reading it first. An intentional full rewrite goes through Edit too, or the
# file is removed first on purpose.
set -uo pipefail

payload="$(cat)"

path="$(printf '%s' "$payload" | python3 -c '
import json,sys
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
print(d.get("tool_input", {}).get("file_path", "") or "")
' 2>/dev/null)"

[ -n "$path" ] || exit 0
[ -f "$path" ] || exit 0
[ -s "$path" ] || exit 0

# Scratchpads and build output are throwaway: rewriting them is the point.
case "$path" in
    /tmp/*|*/storage/*|*/public/build/*|*/node_modules/*|*/vendor/*) exit 0 ;;
esac

lines="$(wc -l < "$path" | tr -d ' ')"

cat >&2 <<EOF
BLOQUEADO: "$path" YA EXISTE ($lines líneas) y Write lo pisa entero.

Write es para archivos NUEVOS. Para cambiar uno que existe:
  1. Leelo (Read) y mirá qué hay adentro — puede no ser lo que el nombre sugiere.
  2. Cambialo con Edit.
Si de verdad va reescrito completo, borralo primero a propósito.
EOF

exit 2
