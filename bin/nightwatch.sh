#!/usr/bin/env bash
#
# Unattended audit of the client panel: up to 3 Claude runs of the `client`
# skill. Each run is gated by the full Pest suite (plus the browser-suite rule)
# before its commit + push, and leaves a summary the next run reads so it
# doesn't loop fixing the same thing.
#
# Usage: bin/nightwatch.sh   (detaches by itself; follow with tail -f bin/nightwatch-output.log)
#
set -uo pipefail

ROOT=/var/www/atendia
SUMMARY="$ROOT/bin/nightwatch-summary.log"
OUTPUT="$ROOT/bin/nightwatch-output.log"
LOCK=/tmp/atendia-nightwatch.lock
MAX_RUNS=3
RUN_TIMEOUT=3h
NOTIFY_NUMBER=5492995529100

# Closing the laptop kills the SSH / VS Code session and everything attached to
# it: re-launch in a new session, detached from the terminal.
if [ "${NIGHTWATCH_DETACHED:-0}" != 1 ]; then
    NIGHTWATCH_DETACHED=1 setsid nohup "$0" "$@" >>"$OUTPUT" 2>&1 </dev/null &
    echo "nightwatch running in background (pid $!). Follow it: tail -f $OUTPUT"
    exit 0
fi

log() { printf '[%s] %s\n' "$(date '+%F %T')" "$*"; }

# Goes out through the app's own EvolutionApi service and connected instance;
# the text travels as an env var so no quoting can break the tinker line.
notify() {
    docker exec -e NIGHTWATCH_TEXT="$1" -w /var/www/html atendia-app php artisan tinker --execute \
        'app(App\Services\EvolutionApi::class)->sendText((string) config("services.evolution.instance"), "'"$NOTIFY_NUMBER"'", (string) getenv("NIGHTWATCH_TEXT"));' \
        >/dev/null 2>&1 || log "WhatsApp notice failed."
}

# Running unattended, a silent abort would look like a night of work that never happened.
abort() {
    log "$1 Aborting."
    notify "AtendIa nightwatch NO arrancó ($(date '+%d/%m %H:%M')): $2"
    exit 1
}

exec 9>"$LOCK"
if ! flock -n 9; then
    abort "Another nightwatch is already running." "ya hay otro nightwatch corriendo."
fi

cd "$ROOT" || abort "Cannot enter $ROOT." "no se pudo entrar a $ROOT."

# Claude refuses --dangerously-skip-permissions as root unless told it's a sandbox.
export IS_SANDBOX=1

report=""

# The last "## Corrida N" section of the summary: the one this run just wrote.
run_section() {
    awk -v head="## Corrida $1 " 'index($0, head) == 1 { buf = ""; found = 1 } found { buf = buf $0 "\n" } END { printf "%s", buf }' "$SUMMARY"
}

# A dirty tree would sneak the owner's unfinished work into an automated commit.
if [ -n "$(git status --porcelain)" ]; then
    abort "Working tree is not clean; commit or stash first." "hay cambios sin commitear en el repo. Commitealos o guardalos con stash y volvé a lanzarlo."
fi

[ -f "$SUMMARY" ] || printf '# Nightwatch — auditoría del panel cliente\n' >"$SUMMARY"
printf '\n# Sesión %s\n' "$(date '+%F %T')" >>"$SUMMARY"

for run in $(seq 1 "$MAX_RUNS"); do
    log "=== Run $run/$MAX_RUNS ==="

    timeout --kill-after=2m "$RUN_TIMEOUT" claude --dangerously-skip-permissions -p "/client Corrida $run de $MAX_RUNS. Leé primero bin/nightwatch-summary.log entero (lo que hicieron las corridas anteriores) y no repitas trabajo ya hecho o descartado. Al terminar, agregá tu sección '## Corrida $run — fecha' al final de ese log con el formato de la skill, incluidas las líneas COMMIT: y ESTADO:. No commitees ni pushees: lo hace el script."
    claude_status=$?
    log "Claude exited with code $claude_status"
    if [ "$claude_status" -eq 124 ] || [ "$claude_status" -eq 137 ]; then
        printf '\n## Corrida %s — %s\n- Cortada por timeout (%s): lo hecho quedó en el árbol sin resumen propio.\nESTADO: PENDIENTE\n' \
            "$run" "$(date '+%F %H:%M')" "$RUN_TIMEOUT" >>"$SUMMARY"
    fi

    section=$(run_section "$run")
    if [ -z "$section" ]; then
        printf '\n## Corrida %s — %s\n- La corrida terminó sin escribir su resumen (ver bin/nightwatch-output.log).\nESTADO: PENDIENTE\n' \
            "$run" "$(date '+%F %H:%M')" >>"$SUMMARY"
        section=$(run_section "$run")
    fi

    state=$(grep -oE '^ESTADO: *(COMPLETO|PENDIENTE)' <<<"$section" | tail -1 | awk '{print $2}')
    subject=$(grep -E '^COMMIT:' <<<"$section" | tail -1 | sed 's/^COMMIT: *//')
    [ -n "$subject" ] || subject="Audit client panel (nightwatch run $run)"

    if [ -z "$(git status --porcelain)" ]; then
        log "No changes to commit."
        outcome="sin cambios"
        printf -- '- Script: sin cambios para commitear.\n' >>"$SUMMARY"
    else
        # Golden rule: the full suite is the commit gate, run once per commit.
        log "Running the full suite as the commit gate..."
        suite_output=$(docker exec -w /var/www/html atendia-app ./vendor/bin/pest --compact 2>&1)
        suite_status=$?
        printf '%s\n' "$suite_output"

        # Same check the PreToolUse hook applies to Claude's own commits.
        browser_ok=1
        if ! echo '{"tool_input":{"command":"git commit"}}' | bash .claude/hooks/require-browser-suite-before-commit.sh 2>/dev/null; then
            browser_ok=0
        fi

        if [ "$suite_status" -eq 0 ] && [ "$browser_ok" -eq 1 ]; then
            git add -A
            git -c user.name=JPMG2 -c user.email=jpmorenog22@gmail.com commit -q \
                -m "$subject" \
                -m "Nightwatch run $run/$MAX_RUNS (client skill). Summary: bin/nightwatch-summary.log" \
                -m "Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>"
            sha=$(git rev-parse --short HEAD)
            if git push -q origin HEAD; then
                log "Committed and pushed $sha."
                outcome="commit $sha pusheado"
                printf -- '- Script: commit %s pusheado (suite en verde).\n' "$sha" >>"$SUMMARY"
            else
                log "Committed $sha but the push failed."
                outcome="commit $sha, PUSH FALLÓ"
                printf -- '- Script: commit %s hecho, el PUSH FALLÓ (queda local).\n' "$sha" >>"$SUMMARY"
            fi
        else
            state=PENDIENTE
            log "Gate failed (suite=$suite_status, browser=$browser_ok); nothing committed."
            outcome="sin commit (tests en rojo o falta suite de browser)"
            {
                printf -- '- Script: NO se commiteó. Los cambios siguen en el árbol; la próxima corrida arranca por acá.\n'
                [ "$browser_ok" -eq 1 ] || printf -- '  - Se tocaron pantallas y la suite de browser no corrió después del último cambio.\n'
                if [ "$suite_status" -ne 0 ]; then
                    printf -- '  - Suite en rojo. Final de la salida:\n'
                    tail -n 40 <<<"$suite_output" | sed 's/^/    /'
                fi
            } >>"$SUMMARY"
        fi
    fi

    [ "$claude_status" -eq 124 ] || [ "$claude_status" -eq 137 ] && outcome="$outcome, cortada por timeout"
    report+="Corrida $run: ${state:-PENDIENTE}, $outcome"$'\n'

    if [ "$state" = COMPLETO ]; then
        log "Run $run reported COMPLETO; stopping."
        break
    fi
done

log "Nightwatch finished."
notify "AtendIa nightwatch terminó ($(date '+%d/%m %H:%M')).
${report}Detalle: bin/nightwatch-summary.log"
