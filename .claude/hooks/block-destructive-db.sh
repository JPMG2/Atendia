#!/usr/bin/env bash
#
# Hook PreToolUse (Bash): BLOQUEA comandos destructivos de base de datos sobre
# la base de trabajo/producción 'atendia'. migrate:fresh/refresh/reset y db:wipe
# DROPEAN tablas y borran datos. Para aplicar migraciones a 'atendia' se usa
# `php artisan migrate` (solo pendientes) o `migrate --path=<archivo>` (solo esa).
#
# Único permitido: si el comando apunta EXPLÍCITAMENTE a 'atendia_testing'
# (los tests reales usan RefreshDatabase vía phpunit.xml, no estos comandos CLI).
#
# Regla relacionada: .ai/guidelines/migraciones-seguras.md
#
set -u

cmd=$(jq -r '.tool_input.command // ""' 2>/dev/null)

case "$cmd" in
    *migrate:fresh* | *migrate:refresh* | *migrate:reset* | *db:wipe*)
        case "$cmd" in
            *atendia_testing*) exit 0 ;;  # explícitamente la base de testing
        esac
        printf '%s\n' "🛑 BLOQUEADO: comando destructivo de BD detectado:" >&2
        printf '   %s\n' "$cmd" >&2
        printf '%s\n' "Sobre 'atendia' está PROHIBIDO migrate:fresh/refresh/reset y db:wipe (borran datos)." >&2
        printf '%s\n' "Usá 'php artisan migrate' (solo pendientes) o 'migrate --path=<archivo>' (solo esa migración)." >&2
        printf '%s\n' "Si DE VERDAD es para testing, incluí DB_DATABASE=atendia_testing explícito en el comando." >&2
        exit 2
        ;;
esac

exit 0
