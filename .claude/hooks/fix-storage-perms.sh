#!/usr/bin/env bash
#
# Hook PostToolUse (Bash): tras CUALQUIER `docker exec` como root sobre
# atendia-app, devuelve storage/ y bootstrap/cache a www-data (uid 82).
#
# Por qué: artisan/pest corridos como root dejan las vistas Blade compiladas
# (storage/framework/views/*.php) root-owned. Después php-fpm corre como
# www-data, no puede hacerles touch() y el sitio revienta con
#   ErrorException: touch(): Utime failed: Operation not permitted
#   (BladeCompiler.php:215)
#
# Es determinístico a propósito: la guía sola no alcanzó — este error se repitió.
#
# Memorias relacionadas: atendia-livewire-cache-perms · atendia-permisos-acl
#
set -u

cmd=$(jq -r '.tool_input.command // ""' 2>/dev/null)

# Cualquier `docker exec` sobre el contenedor dispara la revisión. OJO: acá no
# se excluye el `-u www-data` — un comando COMPUESTO (root && www-data) contiene
# ese flag y aun así deja basura de root (pasó el 2026-09-03: view:clear como
# root && pest como www-data → livewire/views quedó root y el sitio dio 500).
# El `find` de abajo ya decide solo: si no hay archivos de root, no hace nada.
case "$cmd" in
    *"docker exec"*atendia-app*) ;;
    *)
        exit 0
        ;;
esac

offenders=$(docker exec atendia-app \
    find /var/www/html/storage /var/www/html/bootstrap/cache ! -user www-data -print -quit 2>/dev/null)

if [ -z "$offenders" ]; then
    exit 0
fi

if docker exec atendia-app \
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null; then
    printf '%s\n' '{"systemMessage":"storage/ y bootstrap/cache devueltos a www-data: evita el touch() Utime failed de Blade"}'
fi

exit 0
