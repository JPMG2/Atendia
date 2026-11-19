#!/usr/bin/env bash
#
# Hook PreToolUse (Bash): todo commit de AtendIa que toca codigo DECIDE la version
# que ven los clientes (pedido de ella, 2026-11-19). El mensaje lleva una linea:
#
#   Version: patch   arreglo o mejora de algo que el cliente ya tenia   1.0.0 -> 1.0.1
#   Version: minor   algo NUEVO que el cliente puede usar                1.0.1 -> 1.1.0
#   Version: major   cambio grande en como se usa (lo decide ella)       1.1.0 -> 2.0.0
#   Version: none    nada que el cliente vea: solo admin, pruebas, scripts, documentos
#
# Si sube, config/releases.php tiene que traer arriba el numero que corresponde
# (y lang/es/releases.php sus palabras: eso lo vigila GoldenRulesReleasesTest).
# Regla: .ai/guidelines/atendia.md
#
set -u

command=$(jq -r '.tool_input.command // ""' 2>/dev/null)

case "$command" in
    *git\ commit*) ;;
    *) exit 0 ;;
esac

# Solo el repo de la web: la app y Eloquent llevan su propio numero.
case "$command" in
    */var/www/aliadito-app*|*/var/www/eloquent*) exit 0 ;;
esac

root="${ATENDIA_ROOT:-/var/www/atendia}"
cd "$root" || exit 0

# Lo que entraria al commit: el hook corre antes del `git add` del mismo comando,
# asi que se mira el arbol entero contra HEAD.
changed=$(git -c core.quotepath=off status --porcelain --untracked-files=all 2>/dev/null \
    | sed -E 's/^.. //; s/^"//; s/"$//; s/.* -> //' \
    | grep -E '^(app|resources|routes|lang|database|config)/' \
    | grep -vE '^config/releases\.php$')

[ -z "$changed" ] && exit 0

decision=$(printf '%s' "$command" | grep -oE 'Version: (patch|minor|major|none)' | head -1 | awk '{print $2}')

if [ -z "$decision" ]; then
    cat >&2 <<'MSG'
BLOQUEADO: este commit toca codigo y no dice que pasa con la version que ven los clientes.
Sumale al mensaje UNA linea:
  Version: patch   (arreglo o mejora de algo que ya existia)
  Version: minor   (algo nuevo que el cliente puede usar)
  Version: major   (cambio grande; solo si ella lo decidio)
  Version: none    (nada que el cliente vea: solo admin, pruebas, scripts, documentos)
Si sube: el numero nuevo arriba en config/releases.php y sus palabras en lang/es/releases.php.
MSG
    exit 2
fi

[ "$decision" = "none" ] && exit 0

top() { grep -oE "'version' => '[0-9]+\.[0-9]+\.[0-9]+'" | head -1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+'; }

before=$(git show HEAD:config/releases.php 2>/dev/null | top)
now=$(top < config/releases.php)

# La primera version nace en este commit: no hay contra que comparar.
[ -z "$before" ] && exit 0

IFS=. read -r major minor patch <<< "$before"
case "$decision" in
    major) expected="$((major + 1)).0.0" ;;
    minor) expected="$major.$((minor + 1)).0" ;;
    patch) expected="$major.$minor.$((patch + 1))" ;;
esac

if [ "$now" != "$expected" ]; then
    cat >&2 <<MSG
BLOQUEADO: el mensaje dice "Version: $decision" y la version era $before, asi que tiene que pasar a $expected.
Hoy config/releases.php dice ${now:-nada}. Suma arriba:
  ['version' => '$expected', 'date' => '$(date +%Y-%m-%d)', 'kind' => '$decision'],
y sus palabras para los clientes en lang/es/releases.php (entries.'$expected').
MSG
    exit 2
fi

exit 0
