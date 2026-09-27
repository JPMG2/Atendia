#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): nada que suba un negocio se saltea la
# moderación. Una imagen se valida SOLO con AttributeValidator::imageUpload()
# (formatos que la moderación ve + la regla SafeUpload); rechaza (exit 2) un
# mimes: de imagen/pdf o un 'image'/'file' suelto a mano.
#
# Capa C de la receta. La garantía permanente es
# tests/Feature/GoldenRulesUploadModerationTest.php (capa B): mismos patrones,
# si tocás uno, tocá el otro. Fuera de alcance: los forms del admin.
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -f "$file" ] || exit 0

case "$file" in
    */app/Rules/AttributeValidator.php|*/app/Livewire/Forms/Configuration/*|*/app/Livewire/Forms/Admin/*) exit 0 ;;
    */app/*.php|*/resources/views/*.php) ;;
    *) exit 0 ;;
esac

problems=""

grep -qP "mimes:[^'\"]*\\b(png|jpe?g|webp|gif|svg|pdf)\\b" "$file" \
    && problems="${problems}- formatos de imagen a mano: usá AttributeValidator::imageUpload('origen', \$requerido, \$maxKb)\n"

case "$file" in
    */app/Livewire/Forms/*)
        grep -qP "['\"](image|file)['\"]\\s*[,\\]]" "$file" \
            && problems="${problems}- regla 'image'/'file' suelta: la subida pasa por AttributeValidator::imageUpload (moderación)\n"
        ;;
esac

if [ -n "$problems" ]; then
    {
        printf 'Regla de oro incumplida en %s: lo que sube un negocio pasa por moderación.\n' "$file"
        printf '%b' "$problems"
    } >&2
    exit 2
fi

exit 0
