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

# Solo interesa lo que corrió DENTRO del contenedor sin `-u` (es decir, root).
case "$cmd" in
    *"docker exec"*atendia-app*)
        case "$cmd" in
            *"-u www-data"* | *"--user www-data"* | *"-u 82"*)
                exit 0
                ;;
        esac
        ;;
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
