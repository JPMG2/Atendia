#!/usr/bin/env bash
#
# Hook PreToolUse (Bash): una corrida de la suite ENTERA por commit. La segunda
# se bloquea.
#
# La suite tarda ~90s. La regla escrita es "mientras trabajo corro solo lo
# afectado con --filter; la completa una sola vez, antes del commit" — y se
# incumplió el mismo día en que se escribió: 4 corridas completas (~350s) donde
# hacía falta una. Por eso es un hook y no un buen deseo.
#
# El contador se reinicia solo cuando cambia el HEAD: cada commit se gana su
# corrida completa. Además cae a los 60 minutos, para que una sesión larga sin
# commits no quede trabada.
#
# Protocolo permitido:
#   1) mientras trabajás: ./vendor/bin/pest --filter=LoQueTocaste  (NO cuenta)
#   2) una sola corrida completa, justo antes de commitear
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

command=$(jq -r '.tool_input.command // ""' 2>/dev/null)

# Solo la suite de PHP entera. Los browser tests tienen su propio hook.
case "$command" in
    *pest*|*artisan\ test*) ;;
    *) exit 0 ;;
esac

# Con un filtro o un archivo puntual no aplica: eso es exactamente lo que se
# quiere que se corra mientras se trabaja.
case "$command" in
    *--filter*|*tests/*) exit 0 ;;
esac

root="/var/www/atendia"
counter="${TMPDIR:-/tmp}/atendia-full-suite-runs"
head=$(git -C "$root" rev-parse HEAD 2>/dev/null || echo none)

if [ -f "$counter" ]; then
    age=$(( $(date +%s) - $(stat -c %Y "$counter" 2>/dev/null || echo 0) ))
    stored=$(cut -d' ' -f1 "$counter" 2>/dev/null || echo none)

    # Un commit nuevo (o una hora entera) limpia la cuenta.
    if [ "$stored" != "$head" ] || [ "$age" -gt 3600 ]; then
        rm -f "$counter"
    fi
fi

runs=$(cut -d' ' -f2 "$counter" 2>/dev/null || echo 0)
runs=$((runs + 1))
printf '%s %s' "$head" "$runs" > "$counter"

if [ "$runs" -ge 2 ]; then
    cat >&2 <<'MSG'
BLOQUEADO: segunda corrida de la suite ENTERA sin un commit en el medio.

La suite completa es la puerta del commit, no un "a ver cómo va". Cuesta ~90s
cada vez y el usuario los está esperando.

Qué hacer AHORA:
  - Correr solo lo que tocaste:  ./vendor/bin/pest --compact --filter=LoQueTocaste
  - La suite entera, una sola vez, cuando vayas a commitear.

El contador se limpia solo con el próximo commit.
MSG
    exit 2
fi

exit 0
