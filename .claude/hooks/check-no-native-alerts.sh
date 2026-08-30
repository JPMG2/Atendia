#!/usr/bin/env bash
#
# Hook PostToolUse (Write|Edit): REGLA DE ORO — cero avisos nativos del navegador.
#
# En AtendIa todos los avisos salen de <livewire:dialog /> vía la función global
# `dialog.*`. Un alert()/confirm()/prompt() no se tematiza, lo escribe el
# navegador en el idioma del sistema y bloquea el hilo.
#
# Es la capa C de la receta de enforcement. La garantía permanente es el test
# guardián tests/Feature/GoldenRulesDialogTest.php (capa B), que espeja esta
# misma búsqueda. Si tocás una, tocá la otra.
#
# Guía: .ai/guidelines/avisos-y-modales.md
# Receta: .ai/guidelines/reglas-de-oro-enforcement.md
#
set -u

file=$(jq -r '.tool_input.file_path // ""' 2>/dev/null)

# Solo lo que llega al navegador: vistas Blade y JS del front.
case "$file" in
    *resources/views/*.blade.php) ;;
    *resources/js/*.js) ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

# `dialog.confirm(` y `this.accept(` NO son avisos nativos: por eso se exige que
# la llamada no venga precedida de un punto ni de otro identificador.
bare='(^|[^.[:alnum:]_$])(alert|confirm|prompt)[[:space:]]*\('
explicit='window[[:space:]]*\.[[:space:]]*(alert|confirm|prompt)[[:space:]]*\('

hits=$(grep -nEi "$bare|$explicit" "$file" || true)

if [ -n "$hits" ]; then
    cat >&2 <<MSG
REGLA DE ORO VIOLADA — aviso nativo del navegador en ${file##*/}:

$hits

En AtendIa NINGÚN aviso usa alert()/confirm()/prompt(). Todo sale de
<livewire:dialog /> con la función global \`dialog.*\`:

    if (! await dialog.confirm({ title: '…', message: '…', type: 'danger' })) return;
    await dialog.notify({ title: '…', type: 'success' });

Guía: .ai/guidelines/avisos-y-modales.md
MSG
    exit 2
fi

exit 0
