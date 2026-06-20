# Atendia — Fundamentos de diseño (base compartida para web y dashboard)

> Pegá este bloque al inicio de cualquier prompt de construcción, o importá los tokens a tu `tailwind.config.js`. Ambos productos (sitio del negocio y dashboard de clientes) comparten **exactamente** estos fundamentos. Stack objetivo: **Laravel · Livewire · TailwindCSS · n8n · Evolution API**.

---

## 1. Marca

- **Nombre (provisional):** Atendia — *atender + IA*. Tagline: **"Tu negocio, atendido por IA."**
- **Qué es:** atención y publicidad automatizada por WhatsApp. El cliente escribe → n8n enruta a Laravel → el asistente responde, agenda turnos o muestra productos. Cada cliente configura todo desde su dashboard.
- **Rubro-agnóstico:** sirve igual a un cardiólogo que agenda turnos y a una vendedora de golosinas que muestra su catálogo. Regla de oro de diseño: **confiable para un médico, amigable para un comercio.** Ni corporativo frío, ni juvenil.
- **Logo:** globo de chat redondeado con tres nodos (dos blancos + uno coral) = chat + mini flujo de automatización. Wordmark en Sora 800 con "ia" en jade: **Atend·ia**.

---

## 2. Color

Construí siempre con **tokens semánticos**, nunca con el valor crudo, para que light/dark funcionen solos.

### Paletas base
| Token | Hex | Uso |
|---|---|---|
| **Jade (primario)** | | Confianza + conversación |
| `jade-50` | `#E9FBF4` | fondos suaves |
| `jade-100` | `#C7F4E2` | |
| `jade-200` | `#95EACB` | bordes de marca |
| `jade-300` | `#5BDBAF` | dark: brand/links |
| `jade-400` | `#25C490` | dark: brand |
| **`jade-500`** | **`#0EA47A`** | **color de marca (light)** |
| `jade-600` | `#0A8765` | hover |
| `jade-700` | `#0A6B50` | active / bloques oscuros |
| `jade-800` | `#0B5440` | |
| `jade-900` | `#0A4233` | |
| `jade-950` | `#052A21` | texto sobre marca (dark) |
| **Coral (acento)** | | Calidez, energía. **Usar poco** |
| `coral-50` | `#FFF1ED` | |
| `coral-300` | `#FF9C7E` | dark: acento |
| `coral-400` | `#FF7E5A` | |
| **`coral-500`** | **`#FF6A4D`** | **acento (light)** |
| `coral-600` | `#ED4A2B` | hover |
| `coral-700` | `#C5371C` | texto acento sobre soft |
| **Ink (neutros, verde-pizarra)** | | Texto, superficies, bordes |
| `ink-0` | `#FFFFFF` | |
| `ink-25` | `#FBFCFC` | fondo de página (light) |
| `ink-50` | `#F6F8F8` | superficie hundida |
| `ink-100` | `#EDF1F1` | bordes sutiles |
| `ink-200` | `#DEE5E4` | bordes |
| `ink-300` | `#C4CFCD` | bordes fuertes |
| `ink-400` | `#9AA8A6` | texto sutil |
| `ink-500` | `#6E7C7A` | texto apagado |
| `ink-700` | `#3B4645` | cuerpo de texto |
| `ink-900` | `#141A19` | texto fuerte / superficie dark |
| `ink-950` | `#0B0F0F` | fondo dark |

### Estado
`success #16A34A` · `warning #F59E0B` · `danger #E5484D` · `info #3B82F6` (cada uno con fondo `-soft` translúcido).

### Aliases semánticos (lo que usás en el código)
| Alias | Light | Dark |
|---|---|---|
| `surface-page` | `ink-25` | `ink-950` |
| `surface-card` | `ink-0` | `#141A19` |
| `surface-sunken` | `ink-50` | `#0B0F0F` |
| `border-subtle` | `ink-100` | `rgba(255,255,255,.07)` |
| `border-default` | `ink-200` | `rgba(255,255,255,.11)` |
| `text-strong` | `ink-900` | `ink-50` |
| `text-body` | `ink-700` | `ink-300` |
| `text-muted` | `ink-500` | `ink-400` |
| `brand` | `jade-500` | `jade-400` |
| `accent` | `coral-500` | `coral-400` |
| `text-on-brand` | `#fff` | `#052A21` |

> **Theming:** light es el default. Dark se activa con `[data-theme="dark"]` o `.dark` en un wrapper (la estrategia `class` de Tailwind). Toda superficie/texto/borde debe salir de un alias.

### Burbujas de chat (específico del producto)
`bubble-in` (entrante, gris claro) · `bubble-out` (saliente, jade) · `chat-canvas` (fondo del hilo, gris verdoso).

---

## 3. Tipografía

| Rol | Familia | Pesos | Notas |
|---|---|---|---|
| **Display** | **Sora** | 700 / 800 | Titulares, hero, números grandes. `letter-spacing: -0.02em`. |
| **Cuerpo / UI** | **Plus Jakarta Sans** | 400–700 | Todo lo legible. Humanista, amigable. |
| **Mono** | **JetBrains Mono** | 400–600 | Precios, teléfonos, IDs, horarios, nodos n8n. **Nunca** texto corrido. |

Escala (rem): `xs .75` · `sm .875` · `base 1` · `lg 1.125` · `xl 1.25` · `2xl 1.5` · `3xl 1.875` · `4xl 2.25` · `5xl 3` · `6xl 3.75` · `7xl 4.75`. **Mínimo UI: 14px.**

Fuentes vía Google Fonts (sustitutas — reemplazar si hay licenciadas):
```html
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
```

---

## 4. Espaciado, radios, sombras, motion

- **Grid base 4px.** Padding de sección marketing 64–96px; dentro de cards 24px.
- **Radios (amigables):** botones 12px · cards 16px · tiles grandes 28px · pills 999px.
- **Bordes:** hairline, bajo contraste (`border-subtle` / `border-default`). Card = `1px border sutil + sombra suave`. **Nada de cards con borde-izquierdo de color.**
- **Sombras suaves y en capas:** `sm` cards en reposo · `lg` hover/lift · `xl` modales. Dark profundiza las sombras. **Brand glow** (`0 10px 30px rgba(14,164,122,.28)`) solo bajo el CTA primario del hero, una vez por vista.
- **Motion calmo:** `140ms` hovers, `220ms` transiciones, easing `cubic-bezier(.22,1,.36,1)`. Spring `cubic-bezier(.34,1.56,.64,1)` solo en micro-interacciones (knob del switch). Entradas: fade + subida 8–12px. Respetar `prefers-reduced-motion`.

### Estados de interacción
- **Hover:** botón primario oscurece (`brightness .95`) y mantiene sombra; card sube `translateY(-3px)` + borde jade; ghost/icon se rellena con `surface-sunken`.
- **Press:** `scale(.97)` en botones.
- **Focus:** halo suave 4px de `focus-ring` (jade alpha) + outline sólido para teclado. **Nunca** quitar el focus ring.
- **Disabled:** ~55% opacidad, `cursor: not-allowed`.

---

## 5. Iconografía

- **Lucide** (outline, stroke ~2), tamaño 16–20px en UI, 24–28px en tiles. Color = `currentColor` (tematiza solo).
- Glifos clave: `message-circle`/`message-square` (chat), `calendar`/`calendar-check` (turnos), `package`/`store` (productos), `zap` (automatización), `bot` (asistente), `bell` (avisos), `qr-code` (conexión WhatsApp), `clock`, `users`, `settings`.
- **Sin emoji** en la UI (botones, títulos, cards). Unicode solo como marcas funcionales mínimas (`▾`, `↑/↓`).

---

## 6. Voz y copy (Español, LatAm neutro/rioplatense)

- **Verbo primero, cercano:** *"Automatizá tu WhatsApp"*, *"Atendé sin estar"*, *"Configurá tus días de atención"*.
- **Sentence case** en todo (botones, títulos, menús). UPPERCASE solo en eyebrows diminutos con letter-spacing. Nunca Title Case.
- **Concreto, sin jerga:** *"Responde en 2 segundos"*, no *"optimizá tu engagement"*. Hablar del beneficio del usuario, no de lo que construimos nosotros.
- **Sin emoji** en la UI (los del cliente en sus mensajes de chat sí, es su contenido).
- Ejemplos: CTA *"Empezar gratis"* / *"Crear mi asistente"*; éxito *"Listo. Tu asistente ya responde por vos."*; error *"No pudimos conectar tu número. Revisá el QR e intentá de nuevo."*; vacío *"Todavía no cargaste productos. Sumá el primero y tu asistente ya lo podrá ofrecer."*

---

## 7. `tailwind.config.js` (mapeo directo de tokens)

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class', // .dark en <html> activa dark
  content: ['./resources/**/*.blade.php', './resources/**/*.js', './app/**/*.php'],
  theme: {
    extend: {
      colors: {
        jade: { 50:'#E9FBF4',100:'#C7F4E2',200:'#95EACB',300:'#5BDBAF',400:'#25C490',500:'#0EA47A',600:'#0A8765',700:'#0A6B50',800:'#0B5440',900:'#0A4233',950:'#052A21' },
        coral:{ 50:'#FFF1ED',100:'#FFE0D6',200:'#FFC2AF',300:'#FF9C7E',400:'#FF7E5A',500:'#FF6A4D',600:'#ED4A2B',700:'#C5371C',800:'#9B2D18',900:'#7A2715' },
        ink:  { 0:'#FFFFFF',25:'#FBFCFC',50:'#F6F8F8',100:'#EDF1F1',200:'#DEE5E4',300:'#C4CFCD',400:'#9AA8A6',500:'#6E7C7A',600:'#515E5C',700:'#3B4645',800:'#252D2C',900:'#141A19',950:'#0B0F0F' },
        // aliases semánticos vía CSS vars (definidas en app.css, ver abajo)
        brand: 'rgb(var(--brand) / <alpha-value>)',
        accent:'rgb(var(--accent) / <alpha-value>)',
      },
      fontFamily: {
        display: ['Sora','system-ui','sans-serif'],
        sans: ['"Plus Jakarta Sans"','system-ui','sans-serif'],
        mono: ['"JetBrains Mono"','ui-monospace','monospace'],
      },
      borderRadius: { xl:'16px','2xl':'20px','3xl':'28px' },
      boxShadow: {
        sm:'0 1px 3px rgba(11,15,15,.07),0 1px 2px rgba(11,15,15,.05)',
        md:'0 4px 12px rgba(11,15,15,.08),0 2px 4px rgba(11,15,15,.05)',
        lg:'0 12px 28px rgba(11,15,15,.10),0 4px 10px rgba(11,15,15,.05)',
        xl:'0 24px 48px rgba(11,15,15,.14),0 8px 16px rgba(11,15,15,.06)',
        brand:'0 10px 30px rgba(14,164,122,.28)',
      },
      transitionTimingFunction: { out:'cubic-bezier(.22,1,.36,1)', spring:'cubic-bezier(.34,1.56,.64,1)' },
    },
  },
};
```

Para los aliases semánticos light/dark, definí variables en `resources/css/app.css`:
```css
@layer base {
  :root { --brand: 14 164 122; --accent: 255 106 77;
    --surface-page:#FBFCFC; --surface-card:#fff; --text-strong:#141A19; --text-body:#3B4645; --text-muted:#6E7C7A; --border:#DEE5E4; }
  .dark { --brand: 37 196 144; --accent: 255 126 90;
    --surface-page:#0B0F0F; --surface-card:#141A19; --text-strong:#F6F8F8; --text-body:#C4CFCD; --text-muted:#9AA8A6; --border:rgba(255,255,255,.11); }
}
```
> El archivo `styles.css` de este design system ya trae **todos** los tokens completos (incluyendo `-soft`, estados, chat) listos para copiar si preferís CSS vars puras en vez de Tailwind.
