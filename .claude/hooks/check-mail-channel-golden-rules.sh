#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): el correo sale SOLO por el canal de la casa
# (App\Messaging\Channels\Email — una puerta, un ritual de locale+report).
# Un Mail:: crudo fuera de app/Messaging se rechaza en el momento (exit 2).
#
# Capa C de la receta. La garantía permanente es el test guardián
# tests/Feature/GoldenRulesMailChannelTest.php (capa B): tocás uno, tocás el otro.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

case "$file" in
    */app/*.php) ;;
    *) exit 0 ;;
esac

case "$file" in
    */app/Messaging/*) exit 0 ;;
esac

[ -f "$file" ] || exit 0

if grep -qE '\bMail::|Facades\\\\Mail|->notify\(|\bNotification::|Facades\\\\Notification' "$file"; then
    {
        echo "Regla de oro incumplida en $file: el correo sale SOLO por el canal."
        echo "Usá (new Email(\$model, [\$destino], Mailable::class, [args extra]))->send()"
        echo "de App\\Messaging\\Channels\\Email — jamás Mail:: ni notificaciones (->notify, Notification::)."
        echo "Guía: .ai/guidelines/correo-por-canal.md"
    } >&2
    exit 2
fi

exit 0
