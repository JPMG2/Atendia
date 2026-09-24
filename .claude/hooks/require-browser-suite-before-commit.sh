#!/usr/bin/env bash
#
# Hook PreToolUse (Bash): un commit que toca PANTALLAS exige una corrida de la
# suite de browser después del último cambio de esas pantallas.
#
# La suite de browser no corre con la de PHP (es a demanda), y por eso se fue
# quedando atrás: el 2026-09-24 aparecieron 8 tests viejos de golpe (alta con
# login, Descartar con diálogo, catálogo real...) que nadie había corrido desde
# que cambiaron sus pantallas. Regla de la dueña: se corre antes de cada commit
# que toque pantallas. Por eso es un hook y no un buen deseo.
#
# La corrida la anota block-browser-suite-reruns.sh (la marca). Acá solo se
# compara: si alguna pantalla cambió DESPUÉS de la última corrida, se bloquea.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

command=$(jq -r '.tool_input.command // ""' 2>/dev/null)

case "$command" in
    *git\ commit*) ;;
    *) exit 0 ;;
esac

root="/var/www/atendia"
marker="${TMPDIR:-/tmp}/atendia-browser-suite-ran"

# Lo que entraría al commit: el hook corre ANTES del `git add` del mismo comando,
# así que se mira el árbol entero contra HEAD, no solo lo stageado.
changed=$(git -C "$root" status --porcelain 2>/dev/null \
    | sed 's/^...//; s/.* -> //' \
    | grep -E '^resources/(views|css|js)/' || true)

[ -z "$changed" ] && exit 0

ran=$(stat -c %Y "$marker" 2>/dev/null || echo 0)
newest=0

while IFS= read -r file; do
    [ -f "$root/$file" ] || continue
    mtime=$(stat -c %Y "$root/$file")
    [ "$mtime" -gt "$newest" ] && newest=$mtime
done <<< "$changed"

if [ "$newest" -gt "$ran" ]; then
    cat >&2 <<'MSG'
BLOQUEADO: este commit toca pantallas (resources/views, css o js) y la suite de
browser no corrió después de esos cambios.

Qué hacer AHORA:
  - Correr UNA vez: docker exec -w /var/www/html -u www-data atendia-app ./vendor/bin/pest --compact tests/Browser
  - Lo que falle, re-correrlo solo con --filter: si pasa aislado es flake (se anota y se sigue).
  - Después, commitear.
MSG
    exit 2
fi

exit 0
