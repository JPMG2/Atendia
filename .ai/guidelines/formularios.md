# Formularios

## Estructura
- Componente Livewire **SFC** por defecto. El estado, las reglas y el guardado viven en un
  **Form** (`app/Livewire/Forms/*`) que extiende `BaseForm`; el componente solo delega:
  `mount()` → `$form->setup()`, `save()` → `dispatchNotification($form->save())`.
- PROHIBIDO `rules()`, `validationAttributes()` o `$this->validate()` en el componente.
  La validación es `validateServiceData()` del Form; la persistencia va en `tryAction()`.
- El `wire:model` apunta al Form (`form.campo`); el `name` del campo va SIN prefijo.
- La autorización va en la acción (policy / `can`), nunca en ocultar el botón.

## Campos
- Solo componentes: `<x-inputsform.*>` y `<x-ui.*>`. Cero `<input>`, `<select>`,
  `<textarea>` crudos. Todo select es `<x-inputsform.combobox>` (`<x-ui.select>` no se usa).
- El error se autocablea por `name`. Falta un control → se crea el componente con su test
  Pest ANTES de usarlo.
- Elegir fecha o rango: `<x-inputsform.datepicker>` (`fechas.md`).

## Color, tipografía, iconos
- Cero hex de estilo en el markup: todo por token semántico de `app.css`. Un hex que es
  DATO del usuario se aplica por variable (`style="color:{{ $valor }}"`).
- Números, precios, teléfonos, IDs y códigos en `font-mono`; titulares `font-display`.
- Iconos solo `<x-icon name=".." :size=".." />`; glifo nuevo → `config/icons.php` primero.

## Copy
- Todo texto visible sale de `__()`. Base neutra (tuteo) en `lang/es/`; `lang/es_AR/` solo
  overrides de voseo. Sentence case, verbo primero, sin emoji.
- El título de la pestaña es copy: nada de `#[Title('...')]`. En el panel cliente lo pone
  el ítem del menú (`Menu::titleFor()`); en una pantalla full-page, `render()` con
  `$this->view()->title(__('...'))`.

## Layout — aprovechar el ancho
- Los campos van dentro de `<x-catalog.form-row>`: la fila se DECLARA, no la adivina el wrap.
  Fila 1 = identificador corto + nombre (el nombre se lleva el resto); fila 2 = el resto,
  el estado incluido. Toda fila llega al borde derecho.
- El ancho se declara por contenido (`span="code|short|text|long|full"`), nunca en columnas.
  El formulario no topea su ancho. Nada se abrevia ni se trunca.
- El estado es un campo (`<x-inputsform.switch-field>`), no una fila entera para un booleano.
- Orden: identificador → nombre → formato → estado.
- El chrome del maestro vive una sola vez (`<x-catalog.*>` + `resources/js/catalog-master.js`).

## Cierre
- Responsive mobile-first (probar 390px y 900px) · claro/oscuro por tokens · estilo en `app.css`.
- Test Pest en inglés + `view:clear` + `npm run build`. Verificación visual real.

Candados: `GoldenRulesMarkupTest`, `GoldenRulesFormValidationTest`, `GoldenRulesFormLayoutTest`,
`GoldenRulesScreenTitlesTest`, `GoldenRulesFreshClientScreensTest`,
`ClientResponsiveBrowserTest` y sus hooks `check-blade-golden-rules.sh`,
`check-form-validation-golden-rules.sh`, `check-catalog-form-layout.sh`.
