#!/usr/bin/env bash
#
# Cablea un token de LARGA DURACIÓN para Claude Code dentro del contenedor.
#
# ¿Por qué existe? `sync-claude-auth.sh` copia la credencial OAuth del host al
# contenedor. Eso REPARA pero no PREVIENE: el refresh token es de un solo uso y
# rota, así que host y contenedor comparten el mismo y el primero que renueva
# invalida al otro. Resultado: cada tanto el contenedor se queda sin sesión y
# Claude Code se borra los tokens a sí mismo (accessToken/refreshToken vacíos).
#
# La solución definitiva es que el contenedor NO dependa de la credencial del
# host: `claude setup-token` genera un token propio y de larga duración que NO
# rota. Se expone como CLAUDE_CODE_OAUTH_TOKEN y tiene prioridad sobre el
# archivo de credenciales.
#
# El token se cablea por DOS vías, a propósito:
#
#   1. .claude-code.env (host, gitignored) -> docker-compose `env_file`
#      Sobrevive a recrear el contenedor. Requiere `docker compose up -d`.
#
#   2. /root/.claude/settings.json (dentro del contenedor), clave `env`
#      Toma efecto YA, sin recrear el contenedor (y sin perder las extensiones
#      de VS Code, que viven en /root/.vscode-server y no están persistidas).
#
# Uso:
#   ./docker/wire-claude-token.sh [contenedor]            pide el token y lo cablea
#   ./docker/wire-claude-token.sh [contenedor] --verify   solo verifica lo ya cableado
#   ./docker/wire-claude-token.sh [contenedor] --unwire   saca el token de ambas vías
#
# Para OBTENER el token, en el host: `claude setup-token`
#
# Memoria relacionada: atendia-claude-auth-contenedor
#
set -euo pipefail

CONTAINER="atendia-app"
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${PROJECT_DIR}/.claude-code.env"
SETTINGS="/root/.claude/settings.json"
VERIFY_ONLY=0
UNWIRE=0

for arg in "$@"; do
    case "$arg" in
        --verify)  VERIFY_ONLY=1 ;;
        --unwire)  UNWIRE=1 ;;
        -h|--help) sed -n '2,32p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        -*)        printf '%s\n' "🛑 Opción desconocida: $arg" >&2; exit 1 ;;
        *)         CONTAINER="$arg" ;;
    esac
done

die() {
    printf '🛑 %s\n' "$1" >&2
    shift
    if (( $# )); then printf '%s\n' "$@" >&2; fi
    exit 1
}

command -v jq >/dev/null 2>&1 || die "Falta 'jq' en el host."

# El JSON se arma siempre en el HOST: el contenedor (Alpine) no trae jq.
TMP_JSON="$(mktemp)"
trap 'rm -f "$TMP_JSON"' EXIT
chmod 600 "$TMP_JSON"

docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null | grep -q true \
    || die "El contenedor '$CONTAINER' no está corriendo."

# El CLI de Claude dentro del contenedor es el que trae la extensión de VS Code.
# Se elige la versión más alta por si conviven varias.
claude_bin() {
    docker exec "$CONTAINER" sh -c \
        'ls -d /root/.vscode-server/extensions/anthropic.claude-code-*/resources/native-binary/claude 2>/dev/null | sort -V | tail -1'
}

# ------------------------------------------------------------------ --unwire
if (( UNWIRE )); then
    rm -f "$ENV_FILE"
    actual=$(docker exec "$CONTAINER" cat "$SETTINGS" 2>/dev/null || true)
    [[ -n "${actual//[[:space:]]/}" ]] || actual='{}'
    printf '%s' "$actual" \
        | jq 'del(.env.CLAUDE_CODE_OAUTH_TOKEN) | if (.env // {} | length) == 0 then del(.env) else . end' \
        > "$TMP_JSON"
    docker cp "$TMP_JSON" "$CONTAINER:$SETTINGS"
    docker exec "$CONTAINER" chmod 600 "$SETTINGS"
    printf '✅ Token desconectado. El contenedor vuelve a depender de .credentials.json\n'
    printf '   (o sea, de sync-claude-auth.sh y del problema de rotación).\n'
    exit 0
fi

# ------------------------------------------------------------- pedir el token
if (( ! VERIFY_ONLY )); then
    if [[ -n "${CLAUDE_CODE_OAUTH_TOKEN:-}" ]]; then
        TOKEN="$CLAUDE_CODE_OAUTH_TOKEN"
        printf 'ℹ️  Uso el token de la variable de entorno CLAUDE_CODE_OAUTH_TOKEN.\n'
    else
        printf '\n'
        printf '1) En OTRA terminal del host corré:  claude setup-token\n'
        printf '2) Copiá el token que imprime (empieza con sk-ant-oat...)\n'
        printf '3) Pegalo acá abajo (no se ve mientras escribís) y Enter:\n\n'
        printf '   token: '
        read -rs TOKEN
        printf '\n\n'
    fi

    TOKEN="${TOKEN//[$'\t\r\n ']/}"
    [[ -n "$TOKEN" ]] || die "No pegaste ningún token."
    [[ "$TOKEN" == sk-ant-* ]] || die \
        "Eso no parece un token de Claude Code (debería empezar con 'sk-ant-oat')."

    # --- vía 1: archivo de entorno del host (lo lee docker-compose) ----------
    umask 077
    printf 'CLAUDE_CODE_OAUTH_TOKEN=%s\n' "$TOKEN" > "$ENV_FILE"
    chmod 600 "$ENV_FILE"
    printf '✅ Guardado en %s (chmod 600, gitignored)\n' "$ENV_FILE"

    # --- vía 2: settings.json del contenedor (efecto inmediato) -------------
    # OJO: el contenedor es Alpine y NO tiene jq. El JSON se arma acá, en el
    # host, y se copia ya hecho. (Se aprendió rompiéndolo.)
    docker exec "$CONTAINER" mkdir -p /root/.claude
    actual=$(docker exec "$CONTAINER" cat "$SETTINGS" 2>/dev/null || true)
    # Si el archivo no existe (o está vacío) arrancamos de un objeto vacío.
    # OJO: "${actual:-\{\}}" NO sirve — bash deja los backslashes y jq lo rechaza.
    [[ -n "${actual//[[:space:]]/}" ]] || actual='{}'
    printf '%s' "$actual" \
        | jq --arg t "$TOKEN" '.env = ((.env // {}) + {CLAUDE_CODE_OAUTH_TOKEN: $t})' \
        > "$TMP_JSON" \
        || die "El settings.json del contenedor no es JSON válido; no lo piso."
    docker cp "$TMP_JSON" "$CONTAINER:$SETTINGS"
    docker exec "$CONTAINER" chmod 600 "$SETTINGS"
    printf '✅ Cableado en %s:%s (clave env)\n' "$CONTAINER" "$SETTINGS"
fi

# ------------------------------------------------------------- verificación
printf '\n🔎 Verificando...\n'

BIN="$(claude_bin)"
[[ -n "$BIN" ]] || die "No encontré el CLI de Claude dentro del contenedor" \
    "   (¿está instalada la extensión anthropic.claude-code?)."

docker exec "$CONTAINER" cat "$SETTINGS" 2>/dev/null \
    | jq -e '(.env.CLAUDE_CODE_OAUTH_TOKEN // "") | length > 0' >/dev/null \
    || die "El token no quedó en $SETTINGS dentro del contenedor."
printf '   ✔ token presente en settings.json del contenedor\n'

[[ -s "$ENV_FILE" ]] || die "Falta $ENV_FILE en el host."
grep -q '^CLAUDE_CODE_OAUTH_TOKEN=sk-ant-' "$ENV_FILE" \
    || die "$ENV_FILE no tiene un token válido."
printf '   ✔ token presente en %s\n' "$(basename "$ENV_FILE")"

# Prueba REAL y aislada: se esconde .credentials.json y se pide una respuesta.
# Si contesta, el token alcanza solo -> se acabó la dependencia del host.
printf '   … probando SIN .credentials.json (la prueba que importa)\n'
CREDS_C="/root/.claude/.credentials.json"
HIDDEN="/root/.claude/.credentials.json.wiretest"

docker exec "$CONTAINER" sh -c "[ -f $CREDS_C ] && mv $CREDS_C $HIDDEN || true"
set +e
OUT=$(timeout 180 docker exec "$CONTAINER" "$BIN" -p 'responde solo: WIRED' 2>&1)
RC=$?
set -e
docker exec "$CONTAINER" sh -c "[ -f $HIDDEN ] && mv $HIDDEN $CREDS_C || true"

if (( RC == 0 )) && printf '%s' "$OUT" | grep -q 'WIRED'; then
    printf '   ✔ responde sin la credencial del host — token independiente OK\n\n'
    printf '✅ Listo. El contenedor ya NO depende de tu credencial del host.\n'
    printf "👉 En VS Code: F1 -> 'Developer: Reload Window'\n"
    printf '   Al recrear el contenedor, docker-compose reinyecta el token desde %s.\n' "$(basename "$ENV_FILE")"
else
    die "El token NO funcionó por sí solo (código $RC):" \
        "   ${OUT:0:400}" \
        "   La credencial del host quedó restaurada, así que seguís funcionando como antes." \
        "   Revisá que el token sea el que imprimió 'claude setup-token'."
fi
