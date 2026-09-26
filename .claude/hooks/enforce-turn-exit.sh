#!/usr/bin/env bash
#
# Hook Stop: no se cierra un turno con código tocado sin pasar la puerta de
# salida. Nació el 2026-09-26: las reglas de oro con hook casi no se rompían,
# las que solo estaban escritas (checklist, verificación visual, mejoras) sí,
# y un guardián podía quedar en rojo hasta la corrida completa del commit.
#
# 1) Si el turno tocó código: corre los guardianes GoldenRules* + aislamiento.
#    En rojo bloquea, salvo que la respuesta lo diga ("guardián en rojo").
# 2) Si el turno tocó vistas (resources/views|css|js): la respuesta final
#    trae "Checklist de salida", "Verificación visual" y "Mejoras para
#    decidir", y hubo evidencia visual real en el turno (browser test,
#    captura). Sin evidencia, hay que decirlo: "Sin verificación visual".
# 3) Si se tocó una guía de .ai/guidelines: regenera CLAUDE.md (boost:update).
#
# El inicio del turno lo marca inject-work-rules.sh (UserPromptSubmit).
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

payload=$(cat)
session=$(printf '%s' "$payload" | jq -r '.session_id // "none"' 2>/dev/null)
transcript=$(printf '%s' "$payload" | jq -r '.transcript_path // ""' 2>/dev/null)
last_message=$(printf '%s' "$payload" | jq -r '.last_assistant_message // ""' 2>/dev/null)

root="/var/www/atendia"
marker="${TMPDIR:-/tmp}/atendia-turn-${session}"
blocks_file="${marker}.blocks"

[ -f "$marker" ] || exit 0

# Tope anti-bucle: tres rechazos por turno y se deja cerrar.
blocks=$(cat "$blocks_file" 2>/dev/null || echo 0)
[ "$blocks" -ge 3 ] && exit 0

touched=$(cd "$root" && find app resources tests routes database config lang bootstrap \
    -type f -newer "$marker" -not -path '*/node_modules/*' 2>/dev/null)

# Una guía escrita que no llega a CLAUDE.md no existe para el agente (Boost
# 2.10 lo congeló dos días sin que nadie lo notara): se regenera sola.
if [ -n "$(find "$root/.ai/guidelines" -type f -newer "$root/CLAUDE.md" 2>/dev/null)" ]; then
    docker exec -w /var/www/html atendia-app php artisan boost:update --no-interaction >/dev/null 2>&1
fi

[ -n "$touched" ] || exit 0

# Mensajes del turno actual: los posteriores a la marca que dejó el prompt real
# de la dueña. Adivinarlo por el transcript fallaba: una captura leída vuelve
# como un "user" con texto y parecía un prompt nuevo.
turn_entries='[]'
if [ -f "$transcript" ]; then
    since=$(date -u -d "@$(stat -c %Y "$marker")" +%Y-%m-%dT%H:%M:%S)
    turn_entries=$(jq -s --arg since "$since" '
        map(select(.type == "assistant" and ((.timestamp // "") >= $since)))
    ' "$transcript" 2>/dev/null || echo '[]')
fi

if [ -z "$last_message" ]; then
    last_message=$(printf '%s' "$turn_entries" | jq -r '
        [ .[] | .message.content[]? | select(.type == "text") | .text ] | last // ""
    ' 2>/dev/null)
fi

reject() {
    printf '%s' $((blocks + 1)) > "$blocks_file"
    printf '%s\n' "$1" >&2
    exit 2
}

# 1) Guardianes. Rearman atendia_testing (RefreshDatabase): con otra corrida
# de tests viva (la batería de IA, una suite) le borraban la base por debajo.
# Los pest colgados de días no cuentan: solo los de la última hora.
running=$(ps -eo etimes=,args= 2>/dev/null | awk '$1 < 3600 && /vendor\/bin\/pest/ && !/awk/' | wc -l)

if [ "$running" -gt 0 ]; then
    echo "Guardianes NO corridos: hay una corrida de tests en marcha (atendia_testing ocupada)." >&2
elif docker inspect -f '{{.State.Running}}' atendia-app 2>/dev/null | grep -q true; then
    output=$(docker exec -w /var/www/html atendia-app ./vendor/bin/pest --compact \
        --filter='GoldenRules|BusinessIsolation' 2>&1 | sed 's/\x1b\[[0-9;]*m//g')

    if printf '%s' "$output" | grep -qE 'Tests:.*failed'; then
        if ! printf '%s' "$last_message" | grep -qi 'guardián en rojo'; then
            failures=$(printf '%s' "$output" | grep -E 'FAILED|^\s*\+' | head -20)
            reject "BLOQUEADO: hay guardianes de reglas de oro en ROJO y el turno tocó código.

$failures

Qué hacer: arreglar lo que rompió este turno. Si el rojo NO es de este turno
(archivo ajeno), decirlo explícito en la respuesta con la frase \"guardián en rojo\"
y el archivo culpable, y recién ahí cerrar."
        fi
    fi
fi

# 2) Vistas: checklist + verificación visual + mejoras.
if printf '%s\n' "$touched" | grep -qE '^resources/(views|css|js)/'; then
    missing=""
    printf '%s' "$last_message" | grep -qi 'checklist de salida' || missing="$missing \"Checklist de salida\""
    printf '%s' "$last_message" | grep -qi 'verificación visual' || missing="$missing \"Verificación visual\""
    printf '%s' "$last_message" | grep -qi 'mejoras para decidir' || missing="$missing \"Mejoras para decidir\""

    if [ -n "$missing" ]; then
        reject "BLOQUEADO: este turno tocó vistas y la respuesta final no trae:$missing.

Cerrar con esos tres bloques: el checklist de salida recorrido (atendiadesign /
formularios.md §7), la verificación visual hecha y 2–3 mejoras de una línea."
    fi

    evidence=$(printf '%s' "$turn_entries" | jq -r '
        [ .[] | .message.content[]? | select(.type == "tool_use")
          | select(
              (.name | startswith("mcp__claude-in-chrome"))
              or (.name == "Read" and ((.input.file_path // "") | test("\\.(png|jpe?g|webp)$"; "i")))
              or (.name == "Bash" and ((.input.command // "") | test("tests/Browser|screenshot|playwright"; "i")))
            ) ] | length
    ' 2>/dev/null || echo 0)

    if [ "${evidence:-0}" -eq 0 ] && ! printf '%s' "$last_message" | grep -qi 'sin verificación visual'; then
        reject "BLOQUEADO: este turno tocó vistas y no hubo verificación visual real
(ni browser test, ni captura mirada). Un test verde no alcanza (no-mediocre.md §4).

Qué hacer: correr el browser test de la pantalla o mirar una captura. Si de verdad
no se puede, decirlo explícito en la respuesta: \"Sin verificación visual\" y por qué."
    fi
fi

exit 0
