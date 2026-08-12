#!/usr/bin/env bash
#
# Corre los tests de navegador (Pest v4 / Playwright) sin que se cuelguen.
#
# EL PROBLEMA: cada corrida deja vivo un `playwright run-server` huérfano. El
# plugin lo arranca pero nunca lo mata al terminar, así que se van acumulando
# (uno por corrida, cada uno en su puerto). Cuando la RAM del contenedor se
# ajusta, el server nuevo no logra levantar Chromium y no imprime nunca el
# 'Listening on' que el plugin espera. Y ahí está lo peor:
#
#   vendor/pestphp/pest-plugin-browser/src/Playwright/Servers/PlaywrightNpmServer.php
#     $this->systemProcess->setTimeout(0);      <- sin timeout
#     $this->systemProcess->waitUntil(...);     <- espera 'Listening on'
#
# Con timeout 0 esa espera es INFINITA: el cuelgue no falla nunca, se queda
# colgado hasta que alguien lo mata a mano.
#
# LA SOLUCIÓN (dos partes, las dos hacen falta):
#   1. Cosechar los huérfanos ANTES y DESPUÉS de cada corrida -> no se acumulan.
#   2. Correr con `timeout` -> si igual se cuelga, cuesta segundos, no minutos.
#
# Uso:
#   ./docker/run-browser-tests.sh                        todos los tests/Browser
#   ./docker/run-browser-tests.sh tests/Browser/Foo.php  un archivo
#   BROWSER_TEST_TIMEOUT=300 ./docker/run-browser-tests.sh
#
set -uo pipefail

CONTAINER="${ATENDIA_CONTAINER:-atendia-app}"
TIMEOUT="${BROWSER_TEST_TIMEOUT:-180}"

# Sin argumentos, corre toda la carpeta (no está en el testsuite default de
# phpunit.xml a propósito: browser testing es on-demand).
if (( $# == 0 )); then
    set -- tests/Browser
fi

reap() {
    docker exec "$CONTAINER" sh -c '
        pkill -f "playwright run-server" 2>/dev/null
        pkill -f "/usr/bin/chromium" 2>/dev/null
        exit 0
    ' >/dev/null 2>&1
}

orphans() {
    docker exec "$CONTAINER" sh -c 'ps aux | grep -cE "[p]laywright run-server"' 2>/dev/null | tr -dc '0-9' | tail -c 4
}

before=$(orphans)
if (( before > 0 )); then
    printf '🧹 Cosechando %s servidor(es) de Playwright huérfano(s) de corridas anteriores.\n' "$before"
fi
reap

# La salida va a un archivo, NO a la tubería. El server huérfano hereda el stdout
# de pest, así que mientras siga vivo la tubería no cierra y el comando parece
# colgado aunque los tests hayan terminado hace rato (se ve "5 passed" y sigue
# esperando). Escribiendo a un archivo, la corrida termina cuando termina pest.
LOG="/tmp/pest-browser-$$.log"

docker exec -w /var/www/html -u www-data "$CONTAINER" sh -c \
    "timeout -s KILL $TIMEOUT ./vendor/bin/pest $* >$LOG 2>&1"
status=$?

docker exec "$CONTAINER" sh -c "cat $LOG; rm -f $LOG"

reap

if (( status == 137 )); then
    printf '\n🛑 Se colgó y lo corté a los %ss (antes esto costaba 7 minutos de espera).\n' "$TIMEOUT" >&2
    printf '   Reintentá: al cosechar los huérfanos, la próxima corrida suele arrancar limpia.\n' >&2
fi

exit $status
