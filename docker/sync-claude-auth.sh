#!/usr/bin/env bash
#
# Copia la credencial de Claude Code del HOST al contenedor.
#
# ¿Por qué hace falta? La extensión de Claude Code en VS Code (attachado al
# contenedor) levanta su servidor de callback OAuth en un puerto ALEATORIO
# dentro del contenedor. El navegador corre en la máquina del usuario y no
# alcanza ese puerto — VS Code no lo forwardea porque es efímero — así que el
# login cae al "paste your authorization code manually" y nunca completa:
# cada reintento genera puerto, PKCE y state nuevos.
#
# Como el host YA está logueado, se clona su credencial y listo. Después:
# VS Code -> F1 -> "Developer: Reload Window".
#
# Hay que volver a correrlo cuando:
#   - se recrea el contenedor (la credencial es un secreto, no va en el Dockerfile)
#   - el token rota y la extensión vuelve a pedir login
#
# OJO: que venza el ACCESS token no desloguea a nadie — Claude Code lo renueva
# solo con el REFRESH token. El que importa es el refresh; si ese vence, hay que
# loguearse de nuevo en el host (`claude`) ANTES de correr este script.
#
# Protecciones (la copia va en un solo sentido: host -> contenedor):
#   1. Aborta si la credencial del host no es válida o su refresh token venció:
#      copiarla rompería un contenedor que hoy funciona.
#   2. Aborta si la credencial del CONTENEDOR es más nueva que la del host —
#      eso significa que el contenedor refrescó por su cuenta y copiar sería ir
#      para atrás. Se puede forzar con --force.
#   3. Respalda la credencial del contenedor antes de pisarla. Se restaura con
#      --restore.
#
# Uso:
#   ./docker/sync-claude-auth.sh [contenedor]             copiar host -> contenedor
#   ./docker/sync-claude-auth.sh [contenedor] --force     copiar aunque sea ir para atrás
#   ./docker/sync-claude-auth.sh [contenedor] --restore   volver al último respaldo
#
# Memoria relacionada: atendia-claude-auth-contenedor
#
set -euo pipefail

CONTAINER="atendia-app"
CREDS="${HOME}/.claude/.credentials.json"
DEST="/root/.claude/.credentials.json"
FORCE=0
RESTORE=0

for arg in "$@"; do
    case "$arg" in
        --force)   FORCE=1 ;;
        --restore) RESTORE=1 ;;
        -h|--help) sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*)        printf '%s\n' "🛑 Opción desconocida: $arg" >&2; exit 1 ;;
        *)         CONTAINER="$arg" ;;
    esac
done

# Primera línea con el 🛑, las siguientes tal cual (ya vienen indentadas).
die() {
    printf '🛑 %s\n' "$1" >&2
    shift
    if (( $# )); then printf '%s\n' "$@" >&2; fi
    exit 1
}

# Imprime una fecha legible a partir de milisegundos epoch.
fecha() { date -d "@$(( $1 / 1000 ))" '+%Y-%m-%d %H:%M'; }

# Lee un campo de claudeAiOauth desde el JSON que le llega por stdin.
campo() { jq -r --arg k "$1" '.claudeAiOauth[$k] // empty' 2>/dev/null; }

command -v jq >/dev/null 2>&1 || die "Falta 'jq' (se usa para leer la credencial)."

docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null | grep -q true \
    || die "El contenedor '$CONTAINER' no está corriendo."

now_ms=$(( $(date +%s) * 1000 ))
cont_json=$(docker exec "$CONTAINER" cat "$DEST" 2>/dev/null || true)

# ---------------------------------------------------------------- --restore
if (( RESTORE )); then
    ultimo=$(docker exec "$CONTAINER" sh -c "ls -1t ${DEST}.bak-* 2>/dev/null | head -1" || true)
    [[ -n "$ultimo" ]] || die "No hay ningún respaldo en $CONTAINER (${DEST}.bak-*)."
    docker exec "$CONTAINER" cp "$ultimo" "$DEST"
    docker exec "$CONTAINER" chmod 600 "$DEST"
    printf '✅ Restaurado desde %s\n' "$ultimo"
    printf "👉 Ahora en VS Code: F1 -> 'Developer: Reload Window'\n"
    exit 0
fi

# ------------------------------------------------- 1. validar la del HOST
[[ -r "$CREDS" ]] || die \
    "No puedo leer la credencial del host: $CREDS" \
    "   ¿Estás logueado en Claude Code en este host? ¿Corrés con el usuario correcto (probá con sudo)?"

host_json=$(cat "$CREDS")
host_refresh_exp=$(printf '%s' "$host_json" | campo refreshTokenExpiresAt)
host_access_exp=$(printf '%s' "$host_json" | campo expiresAt)

[[ -n "$host_refresh_exp" ]] || die \
    "$CREDS no parece una credencial de Claude Code (falta claudeAiOauth.refreshTokenExpiresAt)."

if (( host_refresh_exp < now_ms )); then
    die "La credencial del HOST está VENCIDA (refresh token expiró el $(fecha "$host_refresh_exp"))." \
        "   Copiarla rompería el contenedor. Logueate primero en el host con 'claude' y volvé a correr esto."
fi

# Que el access token esté vencido es normal: se renueva solo con el refresh.
if [[ -n "$host_access_exp" ]] && (( host_access_exp < now_ms )); then
    printf 'ℹ️  El access token del host está vencido (%s), pero es normal: se renueva solo.\n' \
        "$(fecha "$host_access_exp")"
fi

# --------------------------- 2. no ir para atrás pisando una credencial mejor
if [[ -n "$cont_json" ]]; then
    cont_access_exp=$(printf '%s' "$cont_json" | campo expiresAt)
    if [[ -n "$cont_access_exp" && -n "$host_access_exp" ]] && (( cont_access_exp > host_access_exp )); then
        if (( FORCE )); then
            printf '⚠️  La credencial del contenedor es MÁS NUEVA que la del host; piso igual por --force.\n'
        else
            die "La credencial del CONTENEDOR es más nueva que la del host:" \
                "   contenedor: $(fecha "$cont_access_exp")" \
                "   host:       $(fecha "$host_access_exp")" \
                "   El contenedor refrescó por su cuenta — copiar sería ir para atrás y romperlo." \
                "   Si Claude falla en el HOST, el problema está acá, no allá." \
                "   Para pisarlo igual: $0 $CONTAINER --force"
        fi
    fi
fi

# ------------------------------------------- 3. respaldar antes de sobrescribir
docker exec "$CONTAINER" mkdir -p /root/.claude
if [[ -n "$cont_json" ]]; then
    backup="${DEST}.bak-$(date +%Y%m%d-%H%M%S)"
    docker exec "$CONTAINER" cp "$DEST" "$backup"
    printf '💾 Respaldo de la credencial anterior: %s\n' "$backup"
fi

docker cp "$CREDS" "$CONTAINER:$DEST"
docker exec "$CONTAINER" chmod 600 "$DEST"

printf '✅ Credencial copiada a %s:%s\n' "$CONTAINER" "$DEST"
[[ -n "$host_access_exp"  ]] && printf '   access token vence:  %s\n' "$(fecha "$host_access_exp")"
[[ -n "$host_refresh_exp" ]] && printf '   refresh token vence: %s\n' "$(fecha "$host_refresh_exp")"
printf "👉 Ahora en VS Code: F1 -> 'Developer: Reload Window'\n"
printf '   ¿Salió mal? Volvé atrás con: %s %s --restore\n' "$0" "$CONTAINER"
