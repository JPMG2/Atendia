#!/usr/bin/env bash
#
# Hook PostToolUse (Bash): tras `composer update`/`install` recarga php-fpm
# para vaciar OPcache + JIT. Si no se recarga, el sitio tira 502 Bad Gateway
# porque el bytecode/traces JIT del código viejo quedan mezclados con el
# vendor nuevo y php-fpm segfaultea (SIGSEGV) en cada request.
#
# Memoria relacionada: atendia-composer-update-reload-fpm
#
set -u

cmd=$(jq -r '.tool_input.command // ""' 2>/dev/null)

case "$cmd" in
    *"composer update"* | *"composer install"*)
        if docker exec atendia-app sh -c 'kill -USR2 $(pgrep -o -f "php-fpm: master")' 2>/dev/null; then
            printf '%s\n' '{"systemMessage":"php-fpm recargado: OPcache/JIT limpios tras composer"}'
        fi
        ;;
esac

exit 0
