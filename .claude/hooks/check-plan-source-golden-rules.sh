#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): toda cifra de un plan sale de UN lugar, la
# tabla `plans` (leída por App\Classes\Main\Plan). Rechaza (exit 2) un Blade o
# una traducción con una cifra de plan tipeada a mano, o código que vuelva a
# leer config('atendia.plans'/'atendia.trial').
#
# Capa C de la receta. La garantía permanente es
# tests/Feature/GoldenRulesPlanSourceTest.php (capa B): mismos patrones,
# si tocás uno, tocá el otro.
#
# Guía: .ai/guidelines/planes-fuente-unica.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -f "$file" ] || exit 0
case "$file" in *.php) ;; *) exit 0 ;; esac

problems=""

case "$file" in
    */app/*|*/resources/views/*|*/database/*|*/routes/*|*/config/*)
        grep -qE "atendia\.(plans|trial)\b" "$file" && problems="${problems}- lee planes/prueba de config: usá App\\\\Classes\\\\Main\\\\Plan (tabla plans)\n"
        ;;
esac

case "$file" in
    */lang/*|*/resources/views/*)
        grep -qiP "\d[\d.]*\s+(conversaciones con IA|n[uú]meros? de WhatsApp|minutos de audio|consultas al mes|mensajes por hora|d[ií]as gratis|d[ií]as el plan)" "$file" \
            && problems="${problems}- cifra de plan tipeada a mano: usá un :placeholder y pasá el valor desde Plan\n"
        ;;
esac

case "$file" in
    */resources/views/*)
        grep -qP "'\\\$\d+'" "$file" && problems="${problems}- precio tipeado a mano: sale de Plan (price / annualMonthlyPrice)\n"
        ;;
esac

if [ -n "$problems" ]; then
    {
        printf 'Regla de oro incumplida en %s: los planes tienen UNA sola fuente.\n' "$file"
        printf '%b' "$problems"
        printf 'Guía: .ai/guidelines/planes-fuente-unica.md\n'
    } >&2
    exit 2
fi

exit 0
