#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): valida las reglas de oro de markup sobre el
# archivo .blade.php recién escrito y, si las viola, devuelve el error en el
# momento (exit 2) para corregir antes de correr los tests.
#
# Es la capa C de la receta de enforcement. La garantía permanente es el test
# guardián tests/Feature/GoldenRulesMarkupTest.php (capa B), que espeja estos
# mismos allowlists. Si tocás uno, tocá el otro.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

# Solo vistas Blade dentro de resources/views.
case "$file" in
    *resources/views/*.blade.php) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

# Ruta relativa a resources/views (para matchear allowlists).
rel="${file##*resources/views/}"

# Primitivos y legacy donde un control crudo es legítimo. Las vistas nuevas
# deben usar <x-ui.*>. NO agregar nada nuevo a esta lista: arreglar el archivo.
raw_allow="
components/ui/input.blade.php
components/ui/select.blade.php
components/ui/textarea.blade.php
components/ui/switch.blade.php
components/ui/checkbox.blade.php
components/text-input.blade.php
auth/reset-password.blade.php
"

# Hex permitido solo aquí (tick del checkbox + deuda pre-existente del site).
hex_allow="
components/ui/checkbox.blade.php
components/site/phone-mock.blade.php
components/site/closing-cta.blade.php
components/site/pricing.blade.php
"

in_list() { printf '%s\n' "$2" | grep -qxF "$1"; }

violations=""

if ! in_list "$rel" "$raw_allow" && grep -qiE '<(input|select|textarea)\b' "$file"; then
    violations="${violations}- Usa <input>/<select>/<textarea> crudo. Usá los componentes <x-ui.input/select/textarea/switch/checkbox> (garantizan el foco de un solo anillo, el theming y el wiring del error).\n"
fi

if ! in_list "$rel" "$hex_allow" && grep -qE '#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?\b' "$file"; then
    violations="${violations}- Hardcodea un color hex. Usá tokens semánticos de resources/css/app.css (var(--...)), nunca un hex.\n"
fi

if grep -qiE 'data-lucide|lucide\.createIcons' "$file"; then
    violations="${violations}- Usa Lucide vía data-lucide/createIcons. Los iconos van por <x-icon name=\"...\" :size=\"...\" />.\n"
fi

if [ -n "$violations" ]; then
    printf 'Reglas de oro de markup incumplidas en %s:\n%b\nCorregí el archivo antes de continuar.\n' "$rel" "$violations" >&2
    exit 2
fi

exit 0
