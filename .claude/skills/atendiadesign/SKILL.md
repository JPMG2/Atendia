---
name: atendiadesign
description: Reglas de oro del sistema de diseño de Atendia. Usar SIEMPRE al crear, editar o revisar cualquier interfaz, vista Blade, componente Livewire, página de marketing o pantalla del dashboard de Atendia. Define marca (jade + coral), tipografía (Sora / Plus Jakarta Sans / JetBrains Mono), tokens semánticos, voz/copy en español rioplatense, y los tres mandatos no negociables — responsive, tema claro/oscuro y configuración global en resources/css/app.css.
user-invocable: true
---

# Atendia — Sistema de diseño (reglas de oro)

> **Marca:** Atendia — *"Tu negocio, atendido por IA."*
> Atención y publicidad automatizada por WhatsApp (WhatsApp → n8n → Laravel).
> **Rubro-agnóstico:** la misma UI sirve a un cardiólogo que agenda turnos y a una
> vendedora de golosinas que muestra su catálogo. **Regla madre:** confiable para un
> médico, amigable para un comercio. Ni corporativo frío, ni juvenil.

Stack real del producto: **Laravel 13 · Livewire 4 · TailwindCSS 3 · Alpine.js · n8n · Evolution API (WhatsApp)**.

---

## ⛔ Los tres mandatos NO NEGOCIABLES

Toda pantalla que construyas debe cumplir los tres, sin excepción:

1. **Responsive, mobile-first.** Tanto el sitio como el dashboard colapsan limpio a una
   columna / sidebar en drawer. Probar siempre ≤900px y ≤560px.
2. **Tema claro y oscuro.** Light es el default; dark se activa con la clase `.dark`
   en `<html>` (estrategia `class` de Tailwind; los tokens también responden a
   `[data-theme="dark"]`). El toggle persiste en `localStorage` (`atendia-theme`) y
   se aplica **antes del primer pintado** para evitar el flash. **Jamás hardcodear un
   color**: todo sale de un token semántico para que light/dark funcionen solos.
3. **Configuración global en `resources/css/app.css`.** Todos los tokens (colores,
   tipografía, espaciado, radios, sombras, motion), los helpers semánticos y las
   clases de componente (`.btn`, `.card`, `.badge`, `.navbar-frosted`, marquee) viven
   ahí. Es la única fuente de verdad del diseño. No dupliques tokens en vistas ni en
   `tailwind.config.js` (ahí solo va `darkMode: 'class'` y las familias de fuente).

---

## 1. Color — construir con tokens semánticos, nunca con la escala cruda

- **Primario — Jade** `--brand` (`--jade-500 #0EA47A` en light, `--jade-400` en dark).
  Botones primarios, links, estados activos, brand glow.
- **Acento — Coral** `--accent` (`--coral-500 #FF6A4D`). **Usar poco**: CTA del hero,
  una ilustración, un highlight por vista. Nunca como color de cuerpo o de superficie grande.
- **Neutros — Ink:** pizarra con tinte verde (no gris puro). Texto, superficies, bordes.
- **Estado:** `--success` · `--warning` · `--danger` · `--info`, cada uno con su `-soft`.
- **Aliases que usás en el código:** superficies `--surface-page` / `--surface-card`
  / `--surface-sunken`; texto `--text-strong` / `--text-body` / `--text-muted` /
  `--text-subtle`; bordes `--border-subtle` / `--border-default`; marca `--brand` /
  `--brand-soft` / `--brand-soft-text`; `--text-on-brand`.
- **Chat (producto):** `--bubble-in` / `--bubble-out` / `--chat-canvas`.
- Sin gradientes azul-violeta de SaaS. Permitido: washes jade suaves y glow radial
  jade detrás del hero/feature art.

Helpers de Tailwind ya definidos en `app.css` para no hardcodear:
`text-strong`, `text-body`, `text-muted`, `text-subtle`, `text-brand`, `text-accent`,
`bg-card`, `bg-sunken`, `bg-brand-soft`, `bd-subtle`, `bd-default`, `eyebrow`.

## 2. Tipografía

- **Display — Sora** (700/800, tracking `-0.02em`): titulares, hero, números grandes. Clase `font-display`.
- **Cuerpo/UI — Plus Jakarta Sans** (400–700): todo lo legible. Es `font-sans` (default).
- **Mono — JetBrains Mono:** precios, teléfonos, IDs, horarios, nodos n8n. Clase `font-mono`. **Nunca** texto corrido.
- Escala rem `--text-xs … --text-7xl`. **Mínimo UI: 14px (`--text-sm`).**

## 3. Forma, espaciado, sombras, motion

- Grid base **4px**. Sección marketing 64–96px; dentro de cards 24px. Containers: `--container-xl` (1200) marketing, `--container-2xl` (1320) app.
- Radios amigables: botones 12px, cards 16px, tiles grandes 28px, pills 999px.
- Bordes hairline y de bajo contraste. Card = `1px border sutil + sombra suave`.
  **Nada de cards con borde-izquierdo de color. Nada de cards con emoji.**
- Sombras suaves en capas: `--shadow-sm` reposo, `--shadow-lg` hover, `--shadow-xl` modales.
  **Brand glow** (`--shadow-brand`) solo bajo el CTA primario del hero, una vez por vista.
- Motion calmo: `--dur-fast` (140ms) hovers, `--dur-base` (220ms) transiciones, `--ease-out` por defecto.
  `--ease-spring` solo en micro-interacciones (knob del switch). Respetar `prefers-reduced-motion`.

### Estados de interacción
- **Hover:** botón primario `brightness(.95)` + mantiene sombra; card `translateY(-3px)` + borde jade; ghost/icon se rellena con `--surface-sunken`.
- **Press:** `scale(.97)`. **Focus:** halo de `--focus-ring` + outline; **nunca** quitar el focus ring. **Disabled:** ~55% opacidad, `not-allowed`.
- **Foco de inputs — UN SOLO anillo (regla de oro).** Un campo muestra su foco **únicamente** vía su wrapper (`.field-control:focus-within` → borde jade + halo `--focus-ring`). El `<input>` interno **nunca** dibuja anillo propio: jamás debe verse un segundo borde/glow (el azul nativo del navegador) adentro del campo. Esto ya está blindado en `app.css` (`input:focus` → `outline:none; box-shadow:none`, y el `:focus-visible` global se excluye en input/select/textarea). Si aparece un anillo doble o azul: NO es el componente, es CSS sin recompilar → `npm run build`. Autofill (Chrome/Safari) también se neutraliza ahí para que no pinte el campo de azul.

## 4. Iconografía

- **Lucide** (outline, stroke ~2). 16–20px en UI, 24–28px en tiles. Color = `currentColor` (tematiza solo).
- **SVG inline self-hosted, NO CDN.** Un único componente paramétrico: `<x-icon name="zap" :size="20" />`.
  Los paths viven en el registro central `config/icons.php` (regla DRY). **Nunca** usar `<i data-lucide>` ni `lucide.createIcons()`.
- **Agregar un icono nuevo:** copiar el inner SVG de https://lucide.dev a `config/icons.php` y usarlo por nombre. Color: hereda `currentColor`, o pasar `style="color:var(--brand)"`.
- Glifos clave: `message-circle`/`message-square`, `calendar`/`calendar-check`, `package`/`store`, `zap`, `bot`, `bell`, `qr-code`, `clock`, `users`, `settings`.
- **Sin emoji** como iconografía en la UI (botones, títulos, cards). Los emoji del cliente dentro de un mensaje de chat sí, es su contenido.

## 5. Voz y copy — Español LatAm neutro/rioplatense

- **Verbo primero, cercano:** *"Automatizá tu WhatsApp"*, *"Configurá tus días de atención"*.
- **Sentence case** en todo (botones, títulos, menús). UPPERCASE solo en eyebrows diminutos con letter-spacing. Nunca Title Case.
- **Concreto, sin jerga:** *"Responde en 2 segundos"*, no *"optimizá tu engagement"*. Hablar del beneficio del usuario.
- **Sin emoji** en la chrome de la UI.
- Ejemplos: CTA *"Empezar gratis"* / *"Crear mi asistente"*; éxito *"Listo. Tu asistente ya responde por vos."*; error *"No pudimos conectar tu número. Revisá el QR e intentá de nuevo."*

---

## Librería de componentes UI reutilizables (DRY — usar SIEMPRE, no reinventar)

Componentes Blade en `resources/views/components/` con la convención oficial `<x-...>`.
Antes de escribir markup nuevo, **revisá si ya existe el componente** y reusalo.
Todos son theme-aware (usan tokens → dark/light solos) y blindan props inválidos al default.

- **`<x-icon name="zap" :size="20" />`** — icono Lucide inline (registro `config/icons.php`).
- **`<x-ui.button variant="primary|secondary|ghost|accent" size="sm|md|lg" :href="..." icon="zap" :fullWidth="false">`** — si tiene `href` es `<a>`, si no `<button>`.
- **`<x-ui.icon-button icon="menu" size="sm|md|lg" variant="secondary|ghost" label="...">`** — botón cuadrado; `label` = aria-label obligatorio. Acepta slot para casos especiales (ej. toggle de tema con dos iconos).
- **`<x-ui.badge variant="brand|accent" :dot="false">`** — pill.
- **`<x-ui.card :interactive="false" as="div">`** — superficie (borde sutil + sombra); `interactive` agrega hover lift + borde jade.
- **`<x-ui.label for="...">`** — etiqueta de campo.
- **`<x-ui.input label hint error name icon iconRight size>`** — campo de texto; el `error` se autocompleta del ErrorBag por `name`; pasa `wire:model`, `type`, `placeholder`, etc. al `<input>`.
- **`<x-ui.textarea label hint error name :rows="4">`** — área de texto.
- **`<x-ui.select label error name :options="[...]" placeholder size>`** — `options` acepta `['v'=>'Label']`, lista simple o `[['value'=>..,'label'=>..]]`.
- **`<x-ui.switch name label :checked size="md|sm">`** y **`<x-ui.checkbox name label description :checked>`** — form-native (checkbox real oculto + visual con tokens; `wire:model` funciona).
- **`<x-ui.avatar :src name size="xs|sm|md|lg|xl" status="online|away|offline">`** — imagen o iniciales con tinte determinístico por nombre.
- **`<x-ui.alert variant="info|success|warning|danger|brand" title icon :dismissible>`** — banner; `dismissible` lo cierra con Alpine.
- **`<x-ui.stat-card label value delta trend="up|down|flat" icon tint="brand|accent|info|warning">`** — KPI del dashboard.
- **`<x-ui.tabs :tabs="[['value'=>..,'label'=>..,'icon'=>?,'badge'=>?]]" default>`** — barra de tabs Alpine; los paneles del slot usan `x-show="tab === '...'"`.

**Reglas al crear un componente nuevo:**
1. Vive en `resources/views/components/` (UI genérico → `ui/`; secciones del sitio → `site/`).
2. Convención `<x-...>` (anónimo Blade con `@props`).
3. Cumple los 3 mandatos: responsive, dark/light vía tokens (NUNCA hex hardcodeado), estilo desde `app.css`.
4. Props inválidos caen al default (mapas, no concatenación cruda de clases).
5. Llega con su test Pest (en inglés) que cubre render, variantes y la regla de oro.

## ✅ Checklist de salida — formularios y componentes Livewire (NO NEGOCIABLE)

> Antes de dar por terminado CUALQUIER formulario, vista Blade o componente
> Livewire, verificá uno por uno. Estas reglas están blindadas por el test
> guardián `tests/Feature/GoldenRulesMarkupTest.php` y el hook
> `check-blade-golden-rules.sh` — si no las cumplís, **el build falla**.
> (Receta: `.ai/guidelines/reglas-de-oro-enforcement.md`.)

- [ ] **Campos vía `<x-ui.*>`, jamás controles crudos.** Nada de `<input>`,
      `<select>` ni `<textarea>` sueltos: usar `<x-ui.input>`, `<x-ui.select>`,
      `<x-ui.textarea>`, `<x-ui.switch>`, `<x-ui.checkbox>`. Es lo que garantiza el
      foco de un solo anillo, el theming y el wiring del error por `name`.
- [ ] **Cero color hex.** Todo color sale de un token semántico de `app.css`
      (`var(--brand)`, helpers `text-strong`, `bg-card`, …). Ningún `#fff`/`#0EA47A`.
- [ ] **Iconos solo con `<x-icon name=".." :size=".." />`.** Nunca `<i data-lucide>`
      ni `lucide.createIcons()`.
- [ ] **Reusar antes de crear.** Si ya existe el componente en la librería `<x-ui.*>`,
      usarlo; no reinventar markup.
- [ ] **Test Pest (en inglés)** que cubra el render y la regla relevante.
- [ ] **Responsive + dark/light** verificados (los 3 mandatos de arriba).

## Cómo aplicarlo en este proyecto (Laravel + Blade + Livewire)

- **Tokens y clases globales:** `resources/css/app.css` (ya cargado). Es la fuente de verdad.
- **Tailwind:** `darkMode: 'class'`; fuentes `font-display` (Sora), `font-sans` (Jakarta), `font-mono` (JetBrains).
- **Toggle de tema y menús:** Alpine.js (`x-data`, `@click`, `x-show`, `x-cloak`). El script anti-flash va en el `<head>` del layout (`components/layouts/marketing.blade.php`).
- **Secciones = componentes Blade** chicos en `resources/views/components/site/` (`<x-site.hero />`, `<x-site.pricing />`, …). La landing oficial es `resources/views/welcome.blade.php`.
- **Logos:** `public/assets/logo-mark.svg` (theme-aware vía vars) y `logo-mark-color.svg` (fijo, para favicon/externo).
- No inventes secciones nuevas sin pedirlo. Si una sección queda vacía, es un problema de layout, no de relleno.

## Referencia / origen

El bundle original de Claude Design (tokens completos, componentes React de referencia,
UI kits de website y dashboard, guidelines y prompts de construcción) está en
`reference/` dentro de este skill. La implementación viva en producción está en
`resources/css/app.css` y `resources/views/` — **ante una duda, la implementación
manda; el bundle es la intención de diseño.**
