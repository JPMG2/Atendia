#!/usr/bin/env bash
#
# Hook PreToolUse (Bash): impide re-correr la suite de browser entera más de DOS
# veces seguidas.
#
# Los browser tests de este proyecto fallan solos bajo carga (se cuelgan en el
# timeout, ~12s, y falla un test DISTINTO cada vez). Perseguir ese flake le costó
# al usuario ~8 minutos con el trabajo ya terminado, y la regla escrita —en la
# guía y en la memoria— no alcanzó: se incumplió varios días seguidos. Por eso es
# un hook y no un buen deseo.
#
# Protocolo permitido:
#   1) una corrida completa
#   2) si falla uno, re-correr SOLO ese con --filter (esto NO cuenta)
#   3) si pasa aislado -> es flake: anotarlo y seguir
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

command=$(jq -r '.tool_input.command // ""' 2>/dev/null)

# Solo cuenta la suite ENTERA: con --filter o un archivo puntual no aplica.
case "$command" in
    *pest*tests/Browser*) ;;
    *) exit 0 ;;
esac

case "$command" in
    *--filter*) exit 0 ;;
esac

counter="${TMPDIR:-/tmp}/atendia-browser-suite-runs"

# La ventana se reinicia sola a los 20 minutos: un turno nuevo arranca de cero.
if [ -f "$counter" ]; then
    age=$(( $(date +%s) - $(stat -c %Y "$counter" 2>/dev/null || echo 0) ))
    [ "$age" -gt 1200 ] && rm -f "$counter"
fi

runs=$(cat "$counter" 2>/dev/null || echo 0)
runs=$((runs + 1))
printf '%s' "$runs" > "$counter"

if [ "$runs" -ge 3 ]; then
    cat >&2 <<'MSG'
BLOQUEADO: tercera corrida de la suite de browser entera.

Si un test falló y al re-correrlo con --filter pasa, es un FLAKE de este entorno
(timeout bajo carga, ~12s, test distinto cada vez). No se persigue.

Qué hacer AHORA:
  - Re-correr solo el test que falló:  ./vendor/bin/pest tests/Browser/... --filter="..."
  - Si pasa aislado: anotarlo en una línea en la respuesta y COMMITEAR.

El commit va después de la primera corrida verde, no de la tercera.
MSG
    exit 2
fi

exit 0
