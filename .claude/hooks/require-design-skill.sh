#!/usr/bin/env bash
#
# Hook PreToolUse (Write|Edit|Bash): REGLA DE ORO — ninguna pantalla sin SUS skills.
#
# Nació el 2026-09-27: B3 tocó Conversaciones sin cargar `atendiadesign` y salió
# un visor mediocre; además las vistas se editaron por Bash (python/sed), y así
# ningún hook PostToolUse (Write|Edit) de las reglas de oro llegó a correr.
#   1. Write/Edit sobre resources/views o resources/css exige las skills
#      cargadas en la sesión (se buscan en el transcript).
#   2. Bash que ESCRIBE en resources/views o resources/css queda prohibido.
#
# 2026-10-03, dos arreglos que pidió ella el mismo día que la hicieron fallar:
#   - Eran tres skills y el hook exigía una sola, así que tuvo que recordarme
#     `tailwindcss-development` y `livewire-development` a mano. Ahora las exige
#     el hook: las dos primeras en toda vista, la de Livewire en un SFC (⚡).
#   - El bloqueo de Bash se escapaba con un heredoc: `grep` compara línea por
#     línea, y `python3 - <<'PY'` en una línea con la ruta en otra nunca casaba.
#     El comando se aplana antes de medirlo.
#
# 2026-10-04: el mismo agujero, por otra puerta. El bloqueo de Bash cubría solo
# las vistas, así que `app/`, `database/` y `tests/` se escribieron por heredoc
# y NINGÚN hook de reglas de oro corrió sobre ellos: los comentarios largos
# salieron recién en el guardián, media hora después y con tokens de ella. El
# bloqueo pasa a cubrir TODO lo que un hook PostToolUse vigila. No es un candado
# nuevo: es este mismo, dejando de tener un costado abierto.
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
    # Fuera lo que NUNCA es escribir: /dev/null, 2>&1, y la flecha de PHP
    # (`->price` dentro de un patrón de grep se leía como redirección).
    # El salto de línea pasa a `;`, que los patrones ya tratan como fin de
    # comando: así se miden TODAS las líneas sin que una redirección de la
    # primera se pegue con una ruta de la quinta (aplanar con espacios bloqueó
    # hasta un comando de solo lectura).
    # `git mv` es la ÚNICA excepción: renombra, no escribe. Solo puede mover un
    # archivo ya versionado, o sea contenido que ya pasó las reglas de oro en su
    # ruta vieja. Se neutraliza el token `mv` para que no lo agarre el patrón.
    probe=$(printf '%s' "$cmd" | tr '\n' ';' \
        | sed -E 's#git[[:space:]]+mv#git-rename#g' \
        | sed -E 's#(1>|2>|\&>|>)[[:space:]]*/dev/null##g; s#2>&1##g; s#->##g')

    # El heredoc es el caso que se escapaba: su cuerpo vive en otras líneas, así
    # que se mira el comando ENTERO, pero exigiendo las tres señales juntas.
    blob=$(printf '%s' "$cmd" | tr '\n' ' ')
    # Abrir no es escribir: `open(` a secas marcaba como escritura un
    # `print(open(p).read())`. La señal es el MODO o el método que escribe.
    writes="'w'|\"w\"|\\.write\\(|writelines|file_put_contents|writeFile"
    # Las carpetas que vigila algún hook PostToolUse (el alcance de
    # CommentScanner, más las vistas y el CSS). El borde a la izquierda evita
    # que `atendia-app/` o `midapp/` cuenten como `app/`.
    guarded='(^|[^A-Za-z0-9_.-])(app|database|tests|routes|config|resources)/'

    # El destino de una redirección es una RUTA pegada al `>`: solo caracteres
    # de ruta, sin espacios ni comillas en el medio. Con `[^|;&]*` alcanzaba con
    # que la ruta apareciera EN ALGÚN LADO después de un `>`, y un
    # `grep -n '</div>' app/Foo.php` —solo lectura— quedaba bloqueado
    # (2026-10-04, la segunda vez que este hook frena una lectura).
    target=">[[:space:]]*[\"']?/?([A-Za-z0-9_.~-]+/)*(app|database|tests|routes|config|resources)/"

    if grep -Eq "$target" <<<"$probe" \
        || grep -Eq "(sed -i|perl -i|tee|cp|mv|install)[^|;&]*${guarded}" <<<"$probe" \
        || { grep -Eq "(python3?|php|node)[^|;&]*${guarded}" <<<"$probe" \
            && grep -Eq "(${writes}| -i )" <<<"$probe"; } \
        || { grep -q '<<' <<<"$blob" && grep -Eq "${guarded}" <<<"$blob" \
            && grep -Eq "${writes}" <<<"$blob"; }; then
        echo "BLOQUEADO: no se escribe por Bash (sed/python/heredoc/redirección) en" >&2
        echo "app/ database/ tests/ routes/ config/ resources/." >&2
        echo "Usá Edit o Write: así corren los hooks de reglas de oro sobre el archivo," >&2
        echo "en el acto y no media hora después en un guardián." >&2
        exit 2
    fi
    exit 0
fi

file=$(jq -r '.tool_input.file_path // ""' <<<"$input")
case "$file" in
    *resources/views/*|*resources/css/*) ;;
    *) exit 0 ;;
esac

[ -n "$transcript" ] && [ -f "$transcript" ] || exit 0

# El diseño y las clases valen para TODA vista; Livewire solo donde hay un SFC.
required="atendiadesign tailwindcss-development"

case "$file" in
    *⚡*.blade.php) required="$required livewire-development" ;;
esac

missing=""

for skill in $required; do
    if ! grep -Eq "Launching skill: ${skill}|<command-name>/?${skill}" "$transcript"; then
        missing="${missing} ${skill}"
    fi
done

[ -n "$missing" ] || exit 0

echo "BLOQUEADO: vas a tocar una pantalla sin sus skills cargadas. Falta:${missing}" >&2
echo "Cargalas, repetí la spec en una línea y recién ahí editá." >&2
exit 2
