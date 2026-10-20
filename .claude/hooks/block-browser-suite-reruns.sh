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

# NOMBRAR el binario no es CORRERLO: un `pgrep -f "...pest tests/Browser..."`
# lleva el comando dentro de su patrón y el hook se contaba a sí mismo
# (2026-09-30, los dos contadores quedaron inflados).
case "$command" in
    *pgrep*|*pkill*|*ps\ -*) exit 0 ;;
esac

# Solo cuenta la suite ENTERA: con --filter o un archivo puntual no aplica. Se
# juzga cada tramo del comando por separado: con `pest tests/Eval --filter=x;
# pest tests/Browser` el --filter del primero descartaba la corrida completa
# del segundo y no dejaba la marca (costó 30 min el 2026-10-12, T-01).
is_full_browser_run() {
    local segment="$1"

    case "$segment" in
        *pest*tests/Browser*) ;;
        *) return 1 ;;
    esac

    case "$segment" in
        *--filter*) return 1 ;;
    esac

    # Un archivo puntual tampoco es la suite: sin esto dos corridas de un solo
    # test file consumían la corrida del commit (pasó el 2026-09-26).
    case "$segment" in
        *tests/Browser/*.php*) return 1 ;;
    esac

    return 0
}

full_run=0
# Cada archivo de browser que se corre ENTERO (sin --filter) queda anotado: es lo
# que require-browser-suite-before-commit.sh compara contra lo que el commit toca.
record_files() {
    local segment="$1" file

    case "$segment" in
        *pest*tests/Browser/*.php*) ;;
        *) return 0 ;;
    esac

    case "$segment" in
        *--filter*) return 0 ;;
    esac

    mkdir -p "${TMPDIR:-/tmp}/atendia-browser-ran"

    while IFS= read -r file; do
        touch "${TMPDIR:-/tmp}/atendia-browser-ran/$(basename "$file")"
    done < <(printf '%s\n' "$segment" | grep -oE 'tests/Browser/[A-Za-z0-9_]+\.php')
}

while IFS= read -r segment; do
    record_files "$segment"
done < <(printf '%s\n' "$command" | sed -E 's/(&&|\|\||;|\|)/\n/g')

while IFS= read -r segment; do
    if is_full_browser_run "$segment"; then
        full_run=1
        break
    fi
done < <(printf '%s\n' "$command" | sed -E 's/(&&|\|\||;|\|)/\n/g')

[ "$full_run" -eq 1 ] || exit 0

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

# Corrida permitida: queda anotada para require-browser-suite-before-commit.sh,
# que exige una después del último cambio de pantallas.
touch "${TMPDIR:-/tmp}/atendia-browser-suite-ran"

exit 0
