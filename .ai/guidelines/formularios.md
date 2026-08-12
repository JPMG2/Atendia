# Formularios — receta de calidad (regla de oro)

> Cómo se construye **cualquier** formulario en AtendIa para que salga conforme a la
> primera y pase el blindaje. Un tema por archivo: acá va el *cómo* del form; el
> sistema de diseño (marca, tokens, componentes) vive en el skill `atendiadesign`,
> la seguridad por panel en `arquitectura-paneles.md`, y el enforcement de 3 capas en
> `reglas-de-oro-enforcement.md`. Enlazamos, no repetimos.

Esta regla está **blindada**: el test guardián `tests/Feature/GoldenRulesMarkupTest.php`
y el hook `check-blade-golden-rules.sh` fallan el build si un form la viola. La guía te
evita llegar a ese error; el blindaje lo hace imposible de incumplir.

## Tabla de contenidos

1. Estructura (Livewire SFC)
2. Campos — SOLO `<x-ui.*>` (lo blindado)
3. Color, tipografía, iconos
4. Copy e i18n — el español se adapta a la región
5. Layout / UX (criterio, no blindado)
6. Cierre obligatorio (3 mandatos + test)
7. Checklist de salida

---

## 1. Estructura (Livewire SFC)

- Componente **SFC** por defecto (`resources/views/livewire/...`), no clase+vista
  separada. (Ver memoria `livewire-convenciones`; MFC/class solo por caso justificado.)
- Propiedades públicas **tipadas**, una por campo. `mount()` carga el estado inicial.
- `rules()` con validación **server-side** (Livewire valida como una request HTTP).
- `save()`: `validate()` → persistir → feedback (`$this->dispatch('...')` para el toast).
- **Autorización dentro de la acción** (policy / `can`), NUNCA solo ocultando el botón.
  Ocultar un control es UX; la cerradura va en middleware de ruta y/o policy.

## 2. Campos — SOLO componentes `<x-ui.*>` (esto es lo blindado)

- Usar **siempre** `<x-ui.input>`, `<x-ui.select>`, `<x-ui.textarea>`, `<x-ui.switch>`,
  `<x-ui.checkbox>`. **Cero** `<input>` / `<select>` / `<textarea>` crudos.
- El `error` se **autocablea por `name`** desde el ErrorBag: pasás `name="c.nombre"` y el
  campo muestra su error solo.
- **Foco = un solo anillo** (lo dibuja el wrapper `.field-control`). Si aparece un anillo
  doble o azul nativo → NO es el componente, es CSS sin recompilar → `npm run build`.
- ¿Falta un control (ej. file / dropzone)? **Primero se crea el `<x-ui.*>` con su test
  Pest**, recién después se usa. Nunca un control crudo "por excepción".

## 3. Color, tipografía, iconos

- **Cero hex en el markup.** Todo color por token semántico de `app.css`
  (`text-strong`, `bg-card`, `bg-brand-soft`, `var(--brand)`…). Ningún `#fff`/`#0EA47A`.
  - *Excepción legítima:* un hex que es **dato** que el usuario elige y se guarda (p. ej.
    color de marca en un maestro), aplicado vía **variable** (`style="color:{{ $valor }}"`),
    nunca un literal para estilar. Los swatches viven en PHP, no en Blade.
- Números / precios / teléfonos / IDs / códigos en **`font-mono`**; titulares `font-display`.
- Iconos **solo** `<x-icon name=".." :size=".." />` (16–20px en UI). Nunca `<i data-lucide>`.
  Si falta el glifo → agregarlo a `config/icons.php` **antes** de usarlo.

## 4. Copy e i18n — el español se adapta a la región

> ⚠️ El copy de un form **NO se escribe hardcodeado**. AtendIa sirve **variantes
> regionales de español** (no traducción a otros idiomas): la región de quien nos visita
> cambia el tuteo/voseo. Si escribís el texto fijo en el Blade, rompés esa adaptación.

- Todo texto visible (labels, hints, placeholders, botones, mensajes de éxito/error)
  sale de **archivos de traducción** con `__('...')` / `@lang`, no de strings sueltos.
- **Base neutra en `lang/es/`** = tuteo (*"Conecta tu WhatsApp", "por ti"*). Cubre VE, CO,
  MX, CL y el resto. Acá vive el copy completo del form.
- **`lang/es_AR/`** = SOLO overrides de **voseo** (*"Conectá", "por vos"*). Archivo
  parcial: lo que no está cae a `es`. Agregás override únicamente si hay voseo.
- **`lang/es_VE/`** reservado (tuteo igual que el neutro), para guiños léxicos puntuales.
- Depende de `fallback_locale=es`. La región se resuelve por el middleware `SetLocale`
  (sesión › geo IP › default) y el **selector manual** manda. Detalle completo en la
  memoria `atendia-i18n-variantes-regionales`.
- Estilo (aplica a las tres variantes): **sentence case**, verbo primero, concreto, **sin
  emoji** en la chrome. Errores útiles: *"No pudimos guardar. Revisá el email."*
  (neutro: *"Revisa el email."*).

## 5. Layout / UX (criterio, no blindado)

- **Orden lógico de los campos (regla de oro).** Los inputs se ordenan como los piensa
  quien carga el dato, no como salieron en la tabla: identificador → nombre/descriptivo →
  atributos de formato/visualización → estado (switches `activo` al pie). El formulario se
  lee de arriba a abajo sin saltos.
- **Tamaño adecuado, sin truncar (regla de oro).** Cada input tiene el ancho que su
  contenido necesita: los descriptivos largos (nombre, dirección, URL) van **a lo ancho**
  (`field-wide` / columna completa) para que el texto se vea entero; los cortos (código,
  símbolo, decimales, un número) van chicos y se agrupan de a dos en una fila. Nunca un
  campo tan angosto que oculte la info. Aplica **especialmente a los formularios maestros**.
- Patrón útil: **2 columnas** (`1fr / ~340px`), form a la izquierda y **preview sticky** a
  la derecha; colapsa a 1 col en mobile. **Tabs** si hay muchas secciones.
- Filas "label + descripción a la izquierda / campos a la derecha", apiladas en mobile.
- **Reusar** `<x-ui.card>`, `<x-ui.button>`, `<x-ui.alert>`, `<x-ui.tabs>` — no reinventar
  markup. Revisá la librería `<x-ui.*>` antes de escribir algo nuevo.

## 6. Cierre obligatorio (3 mandatos + test)

- Los 3 mandatos de `atendiadesign`: **responsive mobile-first · claro/oscuro por tokens ·
  estilo desde `app.css`**. Probar ≤900px y ≤560px, y ambos temas.
- **Test Pest en inglés** que cubra render + la regla relevante. Correr la suite en verde
  antes de dar por terminado.
- Tras tocar Blade/CSS: `view:clear` + `npm run build` (si no, la UI se ve rota o el foco
  aparece doble). Ver memorias `frontend-build` y `blaze-rector`.

## 7. Checklist de salida (verificar uno por uno antes de cerrar)

- [ ] Campos 100% vía `<x-ui.*>` — cero controles crudos.
- [ ] **Orden lógico** de los campos (identificador → nombre → formato → estado).
- [ ] **Tamaño adecuado**: descriptivos largos a lo ancho, cortos agrupados; nada truncado.
- [ ] Cero hex de estilo en el markup (solo tokens; hex-dato vía variable si aplica).
- [ ] Iconos solo `<x-icon>`; glifos nuevos agregados a `config/icons.php`.
- [ ] Copy vía traducciones (`__()`), base `es` neutra + override `es_AR` si hay voseo.
- [ ] Autorización en la acción/policy, no solo ocultando el botón.
- [ ] Reusé componentes existentes antes de crear.
- [ ] Test Pest (en inglés) verde; `view:clear` + `npm run build` corridos.
- [ ] Responsive + claro/oscuro verificados.
