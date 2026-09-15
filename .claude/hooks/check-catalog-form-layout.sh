#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): valida las reglas de oro de LAYOUT de los
# formularios maestros sobre el archivo recién escrito y, si las viola, devuelve
# el error en el momento (exit 2) para corregir antes de correr los tests.
#
# Es la capa C de la receta de enforcement. La garantía permanente es el test
# guardián tests/Feature/GoldenRulesFormLayoutTest.php (capa B), que espeja
# estas mismas reglas. Si tocás una, tocá la otra.
#
# Compañero de check-blade-golden-rules.sh, que cubre el MARKUP (controles
# crudos, hex, iconos). Este cubre cómo se reparte el ANCHO.
#
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

[ -f "$file" ] || exit 0

rel="${file##*/var/www/atendia/}"
name="${file##*/}"

violations=""

# ---------------------------------------------------------------------------
# app.css: el formulario de maestro no puede topear su ancho.
# ---------------------------------------------------------------------------
case "$file" in
    *resources/css/app.css)
        if grep -qE '\.catalog-form[^{]*\{[^}]*max-width' "$file" \
            || grep -qE '^\s*\.catalog-master \.catalog-form' "$file"; then
            violations="${violations}- .catalog-form topea su ancho. Un max-width deja espacio muerto a la derecha del panel: el formulario tiene que llegar al borde.\n"
        fi
        ;;
esac

# ---------------------------------------------------------------------------
# Editores de maestro (resources/views/components/catalog/⚡*.blade.php).
# ---------------------------------------------------------------------------
case "$file" in
    *resources/views/components/catalog/⚡*.blade.php)
        case "$name" in
            *placeholder*|*manager*) exit 0 ;;
        esac

        if grep -qE '\bcol-[0-9]+\b|style="[^"]*width|\bw-\[' "$file"; then
            violations="${violations}- Asigna un ancho a mano (col-N / style width / w-[..]). El campo declara QUÉ es con span=\"code|short|text|long|full\" y el ancho lo reparte .catalog-form.\n"
        fi

        if ! grep -q '<x-catalog.form-row' "$file"; then
            violations="${violations}- No declara sus filas. Los campos van dentro de <x-catalog.form-row>: si el corte lo decide el wrap del navegador, el formulario cambia de forma según el ancho de la pantalla y el último campo queda solo en una fila entera.\n"
        fi

        # Cada control del form declara su span (el buscador del toolbar no).
        if grep -oE '<x-inputsform\.[a-z-]+[^>]*>' "$file" \
            | grep -v 'name="q"' \
            | grep -qv 'span='; then
            violations="${violations}- Hay un <x-inputsform.*> sin span=. Sin él el campo cae al ancho descriptivo y un código de 3 letras se lleva el lugar de un nombre.\n"
        fi

        for chrome in 'Alpine.data(' 'class="catalog-table"' 'catalog-formbar' 'catalog-form-foot' 'catalog-toolbar'; do
            if grep -qF "$chrome" "$file"; then
                violations="${violations}- Re-declara el chrome compartido ($chrome). Usá <x-catalog.toolbar|table|form-shell|master> y catalogMaster(): copiarlo hace que un arreglo no llegue a los otros maestros.\n"
            fi
        done

        ;;
esac

# ---------------------------------------------------------------------------
# TODO blade con campos (panel cliente incluido, 2026-09-14): las filas se
# declaran y los selects son el combobox de la casa. Espejo del guardián
# GoldenRulesFormLayoutTest (ratchet congelado ahí; tocar de a dos).
# ---------------------------------------------------------------------------
case "$file" in
    *resources/views/*.blade.php)
        skip=0
        case "$rel" in
            *components/inputsform/*) skip=1 ;;
            *components/business/⚡step-*.blade.php) skip=1 ;;
            *components/client/⚡section-hours.blade.php) skip=1 ;;
            *components/⚡ws-demo.blade.php) skip=1 ;;
            *components/catalog/toolbar.blade.php) skip=1 ;;
            *components/catalog/⚡manager.blade.php) skip=1 ;;
        esac

        if [ "$skip" -eq 0 ]; then
            if grep -qE '^\s*<x-inputsform\.' "$file" \
                && ! grep -q '<x-catalog.form-row' "$file" \
                && ! grep -q 'config-social-row' "$file"; then
                violations="${violations}- Tiene campos <x-inputsform.*> sin UNA fila declarada. Los span= son INERTES fuera de .form-row: la fila queda corta. Envolvé los campos (toolbars incluidas) en <x-catalog.form-row>.\n"
            fi

            case "$rel" in
                *components/ui/select.blade.php) : ;;
                *)
                    if grep -q '<x-ui.select' "$file"; then
                        violations="${violations}- Usa <x-ui.select>. El estándar del proyecto es <x-inputsform.combobox> (búsqueda sin acentos, limpiar, hidden con wire:model).\n"
                    fi
                    ;;
            esac
        fi
        ;;
esac

if [ -n "$violations" ]; then
    printf 'Reglas de oro de layout incumplidas en %s:\n%b\nCorregí el archivo antes de continuar.\n' "$rel" "$violations" >&2
    exit 2
fi

exit 0
