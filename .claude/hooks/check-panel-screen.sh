#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): una pantalla del panel admin no existe sola.
# Al escribir la vista se exige, EN EL ACTO, que ya tenga su ruta en
# routes/admin.php y su ítem de menú en MenuSeeder.php.
#
# Nació el 2026-10-03, al arrancar el panel admin. Los tres guardianes que
# cerraron los defectos del panel cliente (pestaña con nombre, render con la
# base vacía, 390/900px) recorren SOLO `panel = 'client'`, así que una pantalla
# admin nueva podía nacer sin ruta, sin menú y sin que nada lo dijera hasta
# que alguien la buscara a mano. La víctima que lo probó al revés sigue viva:
# `menu.admin_users` se dibuja en el menú y no lleva a ninguna parte.
#
# Es la capa C y no tiene gemelo en capa B a propósito: lo que mira son tres
# ARCHIVOS (vista, rutas, seeder), que es justo lo que un PostToolUse ve. El
# caso inverso (un ítem de menú sin ruta) necesita la BD y va de guardián
# Pest — anotado en pendientes-admin.md §D.
#
# Guía: .ai/guidelines/arquitectura-paneles.md · controles-vivos.md
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -n "$file" ] || exit 0

root="/var/www/atendia"
routes="$root/routes/admin.php"
seeder="$root/database/seeders/MenuSeeder.php"

[ -f "$routes" ] && [ -f "$seeder" ] || exit 0

# Las dos formas que toma una pantalla del admin: un SFC de Livewire bajo
# components/admin/ (con el ⚡ que lo marca) o una vista plana en views/admin/.
case "$file" in
    # The screen lives under its menu option (arquitectura-paneles.md), so the
    # component name is the whole path after components/, dots for slashes.
    *resources/views/components/admin/*⚡*.blade.php)
        rel="${file##*resources/views/components/}"
        component="$(printf '%s' "${rel%.blade.php}" | tr '/' '.' | tr -d '⚡')"
        needle="'${component}'"
        ;;
    *resources/views/admin/*.blade.php)
        base="${file##*/admin/}"
        component="admin.${base%.blade.php}"
        needle="view('${component}')"
        ;;
    *) exit 0 ;;
esac

# Excepciones, cada una con su razón escrita (un allowlist que no crece sin
# una razón que se pueda leer):
#   ws-demo → prueba de vida del WebSocket, se va cuando exista el chat real.
case "$component" in
    admin.ws-demo) exit 0 ;;
esac

[ -f "$file" ] || exit 0

problems=""

if ! grep -qF "$needle" "$routes"; then
    problems="${problems}- Sin ruta: ${needle} no aparece en routes/admin.php. Una pantalla sin ruta no se puede abrir.\n"
    route_name=""
else
    # El nombre completo lo arma el grupo (prefijo admin.), así que de la línea
    # de la ruta solo sale el sufijo.
    # El `--` no es adorno: el patrón arranca con `-` y grep lo leería como opción.
    suffix=$(grep -F "$needle" "$routes" | grep -oE -- "->name\('[^']+'\)" | head -1 | sed -E "s/->name\('([^']+)'\)/\1/")
    route_name="admin.${suffix}"

    if [ -z "$suffix" ]; then
        problems="${problems}- La ruta de ${component} no tiene ->name(): el menú y los links la nombran por ahí.\n"
        route_name=""
    fi
fi

if [ -n "$route_name" ] && ! grep -qF "'${route_name}'" "$seeder"; then
    problems="${problems}- Sin ítem de menú: '${route_name}' no aparece en MenuSeeder.php. Una pantalla a la que no se llega desde el menú es una pantalla muda.\n"
fi

if [ -n "$problems" ]; then
    printf 'Pantalla del panel ADMIN incompleta (%s):\n%b\nQué hacer: sumá primero la ruta en routes/admin.php y la fila en\nMenuSeeder.php (con su clave en lang/es/menu.php), y después la vista.\nGuía: .ai/guidelines/arquitectura-paneles.md\n' \
        "${file#"$root"/}" "$problems" >&2
    exit 2
fi

exit 0
