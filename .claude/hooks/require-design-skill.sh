#!/usr/bin/env bash
#
# Hook PreToolUse (Write|Edit|Bash): REGLA DE ORO — ninguna pantalla sin la skill de diseño.
#
# Nació el 2026-09-27: B3 tocó Conversaciones sin cargar `atendiadesign` y salió
# un visor mediocre; además las vistas se editaron por Bash (python/sed), y así
# ningún hook PostToolUse (Write|Edit) de las reglas de oro llegó a correr.
#   1. Write/Edit sobre resources/views o resources/css exige la skill
#      `atendiadesign` cargada en la sesión (se busca en el transcript).
#   2. Bash que ESCRIBE en resources/views o resources/css queda prohibido.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u
input=$(cat)
tool=$(jq -r '.tool_name // ""' <<<"$input")
transcript=$(jq -r '.transcript_path // ""' <<<"$input")

if [ "$tool" = "Bash" ]; then
    cmd=$(jq -r '.tool_input.command // ""' <<<"$input")

    # Nombrar una vista no es escribirla. La versión anterior miraba si el
    # comando traía `>` en cualquier lado y bloqueó un comando de SOLO LECTURA
    # por su `>/dev/null` (2026-10-02). Se mira el destino de la escritura.
    probe=$(sed -E 's#(1>|2>|\&>|>)[[:space:]]*/dev/null##g; s#2>&1##g' <<<"$cmd")
    view='resources/(views|css)'

    if grep -Eq ">[[:space:]]*\"?[^|;&]*${view}" <<<"$probe" \
        || grep -Eq "(sed -i|perl -i|tee|cp|mv|install)[^|;&]*${view}" <<<"$probe" \
        || { grep -Eq "(python3?|php|node)[^|;&]*${view}" <<<"$probe" \
            && grep -Eq "(open\(|'w'|\"w\"|file_put_contents|writeFile| -i )" <<<"$probe"; }; then
        echo "BLOQUEADO: las vistas y el CSS no se escriben por Bash (sed/python/redirección)." >&2
        echo "Usá Edit o Write: así corren los hooks de reglas de oro sobre el archivo." >&2
        exit 2
    fi
    exit 0
fi

file=$(jq -r '.tool_input.file_path // ""' <<<"$input")
case "$file" in
    *resources/views/*|*resources/css/*) ;;
    *) exit 0 ;;
esac

if [ -n "$transcript" ] && [ -f "$transcript" ] \
    && grep -Eq 'Launching skill: atendiadesign|<command-name>/?atendiadesign' "$transcript"; then
    exit 0
fi

echo "BLOQUEADO: vas a tocar una pantalla sin la skill 'atendiadesign' cargada." >&2
echo "Cargala (y 'tailwindcss-development' si hay clases), repetí la spec en una línea y recién ahí editá." >&2
exit 2
