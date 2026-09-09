#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): un componente Livewire (clase O el bloque PHP
# de un SFC) jamás valida por su cuenta — nada de rules()/validationAttributes()
# inline ni $this->validate(). El estado, las reglas y el guardado viven en un
# Form que extiende BaseForm (validateServiceData() + AttributeValidator); el
# componente solo delega. Si el archivo recién escrito valida inline, devuelve
# el error en el momento (exit 2).
#
# Es la capa C de la receta de enforcement. La garantía permanente es el test
# guardián tests/Feature/GoldenRulesFormValidationTest.php (capa B), que espeja
# estos mismos patrones. Si tocás uno, tocá el otro. Sin allowlist: nació en
# cero (2026-09-09) y en cero se queda.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

# Solo donde vive código de componentes: vistas Blade, Livewire y traits.
case "$file" in
    *resources/views/*.blade.php) ;;
    *app/Livewire/*.php) ;;
    *app/Traits/*.php) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

# La puerta legítima del Form, validateServiceData(), NO matchea: el paréntesis
# tiene que seguir al verbo.
if grep -qE 'function[[:space:]]+(rules|validationAttributes)[[:space:]]*\(|\$this->validate(Only)?[[:space:]]*\(' "$file"; then
    {
        printf 'Regla de oro incumplida en %s: un componente no valida inline.\n' "$file"
        printf 'La validación vive en un Form que extiende BaseForm (patrón\n'
        printf 'validateServiceData() + AttributeValidator); el componente delega\n'
        printf '(public XForm $form; save() → dispatchNotification($form->save())).\n'
        printf 'Guía: .ai/guidelines/formularios.md §1\n'
    } >&2
    exit 2
fi

exit 0
