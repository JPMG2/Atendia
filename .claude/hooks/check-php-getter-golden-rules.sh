#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): en una clase PHP pura (app/Classes, app/Dto)
# un getter público sin argumentos se escribe como property hook de PHP 8.4
# (public Tipo $x { get => ... }), nunca como método. Las conversiones (to…,
# from…), los métodos mágicos (__*), los estáticos y todo lo que reciba
# argumentos siguen siendo métodos. Modelos Eloquent y Livewire quedan FUERA.
#
# Es la capa C de la receta de enforcement. La garantía permanente es el test
# guardián tests/Feature/GoldenRulesPropertyHooksTest.php (capa B), que espeja
# este mismo patrón. Si tocás uno, tocá el otro. Sin allowlist: nació en cero
# (2026-09-11) y en cero se queda.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

case "$file" in
    */app/Classes/*.php) ;;
    */app/Dto/*.php) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

if grep -Pq '(?<!static )public\s+function\s+(?!to[A-Z]|from[A-Z]|__)[a-zA-Z_]\w*\s*\(\s*\)' "$file"; then
    {
        printf 'Regla de oro incumplida en %s: un getter sin argumentos de una\n' "$file"
        printf 'clase PHP pura va como property hook (public Tipo $x { get => ... }),\n'
        printf 'no como método. Conversiones to…/from…, mágicos y estáticos quedan\n'
        printf 'como métodos. Guía: .ai/guidelines/clases-php-modernas.md\n'
    } >&2
    exit 2
fi

exit 0
