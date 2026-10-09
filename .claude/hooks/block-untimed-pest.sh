#!/usr/bin/env bash
#
# Hook PreToolUse (Bash): una corrida de pest dentro del contenedor lleva su
# `timeout` ADENTRO del `docker exec`, nunca afuera ni ausente.
#
# Sin `timeout` interno, lo que mata a un cliente de `docker exec` (un
# Ctrl+C, un turno frenado, el tope de la herramienta) deja el pest vivo y con
# su `playwright run-server` colgado. Dos de esos huérfanos vivieron 21 horas
# (2026-10-08) y hacían creer que "ya hay una prueba corriendo". Afuera del
# `docker exec` es peor: mata al cliente y deja el proceso real.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

command=$(jq -r '.tool_input.command // ""' 2>/dev/null)

# NOMBRAR el binario no es CORRERLO (mismo criterio que los otros hooks de suite).
case "$command" in
    *pgrep*|*pkill*|*ps\ -*|*--list-tests*) exit 0 ;;
esac

# El binario EXACTO: `vendor/bin/pest-algo` es otro nombre, no una corrida.
if ! [[ "$command" =~ docker[[:space:]]+exec.*vendor/bin/pest([[:space:]\'\"]|$) ]]; then
    exit 0
fi

before="${command%%docker exec*}"
after="docker exec${command#*docker exec}"

if [[ "$before" =~ timeout[[:space:]]+[0-9] ]]; then
    reason="el timeout esta AFUERA del docker exec: mata al cliente y deja el pest vivo"
elif ! [[ "$after" =~ timeout[[:space:]]+[0-9] ]]; then
    reason="la corrida no tiene timeout: si algo corta al cliente, el pest queda vivo para siempre"
else
    exit 0
fi

cat >&2 <<MSG
BLOQUEADO: $reason.

Correcto (el timeout va DESPUES del docker exec, y adentro):
  docker exec -w /var/www/html atendia-app timeout 580 vendor/bin/pest <archivo> > /tmp/p.log 2>&1
o con shell:
  docker exec -w /var/www/html atendia-app sh -c 'timeout 580 vendor/bin/pest <archivo> > /tmp/p.log 2>&1'
MSG
exit 2
