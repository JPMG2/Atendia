# Atendia — Design System

> **Atendia** (working name — *atender* + *IA*) is an advertising & customer-service automation platform. It connects **WhatsApp → n8n → Laravel** so any business can be *attended by AI*: a customer messages on WhatsApp, n8n routes the message to the Laravel backend, and the assistant answers, books appointments, or shows products automatically. Each client also gets a **dashboard** to configure their availability, capacity, prices, products, and services.

The platform is rubro-agnostic: the same product serves **a cardiologist managing medical turnos** and **a candy seller showing her catalog**. That duality drives every design decision — it must feel **trustworthy enough for a doctor, friendly enough for a small shop**: not too corporate, not too playful.

**Tech stack the product is built on:** Laravel · Livewire · TailwindCSS · n8n · Evolution API (WhatsApp).

> ⚠️ **Working brand.** No name, logo, or fonts were provided. "Atendia", the jade+coral palette, the speech-bubble mark, and the Google-Font typefaces (Sora / Plus Jakarta Sans / JetBrains Mono) are all proposals — rename or replace freely. See **Caveats** at the bottom.

---

## Sources

No codebase, Figma, or brand assets were attached. This system was built from the product brief plus research into comparable WhatsApp-automation / chatbot SaaS products (WhatsCRM, WhatsMark, Pabbly Chatflow, Landbot, ManyChat). If you have a real codebase, Figma file, or brand kit, attach it via the Import menu and we'll align the system to it.

---

## Content fundamentals

How Atendia writes. The product talks to non-technical owners across every rubro, so copy is **plain, warm, and confident — never corporate or techy.**

- **Language:** Spanish (Latin-American, neutral/rioplatense friendly). Verb-first, action-oriented.
- **Voice — "vos/tú" → we use the customer's own register.** Marketing site uses **"vos / tu negocio"** (close, encouraging): *"Automatizá tu WhatsApp"*, *"Atendé sin estar"*. Dashboard uses second person too but calmer and instructional: *"Configurá tus días de atención"*.
- **We = the product, lightly.** Avoid "nosotros" chest-beating. Lead with what the *user's* business gains, not what we built.
- **Casing:** Sentence case everywhere — buttons, headings, menus. Reserve UPPERCASE only for tiny eyebrows/overlines (with letter-spacing). Never Title Case headlines.
- **Tone:** reassuring and concrete. Prefer *"Tu número ya responde solo"* over *"Optimizá tu engagement omnicanal"*. No jargon (no "engagement", "leads", "funnel") on the marketing surface; mild domain words ("turnos", "catálogo", "conversaciones") are fine.
- **Numbers & specifics beat adjectives:** *"Responde en 2 segundos"*, *"24 turnos hoy"*, not *"súper rápido"*.
- **Emoji:** **not** part of the brand UI. Do not decorate buttons, headings, or cards with emoji. (A customer's *own* WhatsApp messages may contain emoji — that's their content, fine in chat bubbles.)
- **Microcopy examples:**
  - CTA primary: *"Empezar gratis"*, *"Crear mi asistente"*
  - CTA secondary: *"Ver cómo funciona"*, *"Agendar demo"*
  - Empty state: *"Todavía no cargaste productos. Sumá el primero y tu asistente ya lo podrá ofrecer."*
  - Success: *"Listo. Tu asistente ya responde por vos."*
  - Error: *"No pudimos conectar tu número. Revisá el código QR e intentá de nuevo."*

---

## Visual foundations

The vibe: **modern, clean, optimistic, breathable.** A confident jade-green system (a nod to WhatsApp's world without copying its lime), warmed by a coral accent so it never feels clinical. Lots of whitespace, soft rounded surfaces, gentle motion.

### Color
- **Primary — Jade** (`--brand` = `--jade-500` `#0EA47A`). Deeper and more sophisticated than WhatsApp green; signals messaging + trust. Drives primary buttons, links, active states, brand glow.
- **Accent — Coral** (`--accent` = `--coral-500` `#FF6A4D`). Warmth and energy. Used **sparingly** — hero CTAs, illustrations, one highlight per view. Never as a body or large-surface color.
- **Neutrals — Ink.** A subtly green-tinted slate (not pure gray) so neutrals harmonize with jade. Carries text, surfaces, borders.
- **Status:** success green (distinct from brand), amber warning, red danger, blue info — each with a `-soft` background token.
- **Theming is first-class.** Every surface/text/border is a **semantic token** (`--surface-card`, `--text-strong`, `--border-default`…). Light is `:root`; dark overrides live under `[data-theme="dark"]` and `.dark`. **Always build with semantic tokens, never raw scale values**, so light/dark just work.

### Type
- **Display — Sora** (700/800, tracking `-0.02em`): headlines, hero, big numbers. Geometric and confident.
- **Body/UI — Plus Jakarta Sans** (400–700): everything readable. Humanist, friendly, neutral.
- **Mono — JetBrains Mono:** numbers, prices, phone numbers, IDs, n8n flow nodes — never body text.
- Scale is rem-based; UI minimum is 14px (`--text-sm`), hero up to `--text-7xl`.

### Backgrounds
- Predominantly **flat solid surfaces** (`--surface-page` / `--surface-card`). No noisy textures.
- **Soft jade tint washes** (`--brand-soft`) and very subtle radial glows behind hero/feature art are allowed. **Avoid blue-purple SaaS gradients entirely.**
- Marketing hero may use a faint jade→white vertical wash; product surfaces stay flat.

### Spacing & layout
- 4px base grid (`--space-*`). Generous: section padding `--space-16`/`--space-24` on marketing, `--space-6` inside cards.
- Containers cap at `--container-xl` (1200) for marketing, `--container-2xl` (1320) for app shells. Dashboard uses a `--sidebar-w` (264px) + `--topbar-h` (64px) shell.
- Everything is **responsive, mobile-first** — both the marketing site and the dashboard must collapse cleanly to one column / a drawer sidebar.

### Corners, borders, cards
- **Friendly, generous rounding.** Default card radius `--radius-lg` (16px); buttons 12px; pills 999px; large feature tiles up to `--radius-2xl` (28px).
- Borders are **hairline and low-contrast** (`--border-subtle` / `--border-default`). Cards = `1px subtle border + soft shadow`, not heavy outlines.
- **No "colored left-border accent" cards.** No emoji cards.

### Shadows / elevation
- **Soft, layered, low-contrast.** `--shadow-sm` for resting cards, `--shadow-lg` on hover/lift, `--shadow-xl` for modals/popovers. Dark mode deepens the shadows.
- **Brand glow** (`--shadow-brand`) under the primary hero CTA only — a signature touch, used once per view.

### Motion
- **Calm and quick.** `--dur-fast` (140ms) for hovers, `--dur-base` (220ms) for most transitions, `--ease-out` as the default curve.
- **`--ease-spring`** for playful micro-interactions only (switch knob, toggle). Avoid bounce on layout.
- Entrances: gentle fade + 8–12px rise. No parallax, no infinite decorative loops in product UI. Respect `prefers-reduced-motion`.

### Interaction states
- **Hover:** primary buttons darken slightly (`brightness 0.95`) + keep shadow; cards lift `translateY(-3px)` + gain a jade border; ghost/icon buttons fill with `--surface-sunken`.
- **Press:** subtle `scale(0.97)` on buttons.
- **Focus:** 4px soft `--focus-ring` halo (jade at low alpha) + solid outline for keyboard nav. Never remove focus rings.
- **Disabled:** ~55% opacity, `not-allowed` cursor.

### Imagery
- Warm, real, human photography (small-business owners, clinics, shops) — bright and optimistic, not cold stock corporate. Rounded with `--radius-lg`/`--radius-xl`.
- Client logos in the testimonial carousel sit in neutral pill/cards, grayscale-to-color on hover.
- Avoid AI-glossy 3D renders and blue-purple abstract blobs.

### Transparency & blur
- Used sparingly: a **frosted sticky navbar** (`backdrop-filter: blur` over a translucent `--surface-card`) on the marketing site; overlay scrims for modals (`--surface-overlay`). Not on product cards.

---

## Iconography

- **Library: [Lucide](https://lucide.dev)** — the consistent icon system for the whole product. Clean, rounded, ~1.75–2px stroke, which matches Atendia's friendly-but-precise tone. Loaded from CDN (`https://unpkg.com/lucide`), rendered via `data-lucide` attributes + `lucide.createIcons()`, or as React nodes passed into components (`leftIcon`, `icon`).
  - ⚠️ **Substitution flag:** No icon set was provided, so Lucide is a *chosen default*. If the real product uses Heroicons, Phosphor, Tabler, or a custom set, swap the CDN link and we'll re-document.
- **Stroke, not fill.** Use outline icons at 16–20px in UI, up to 24–28px in feature tiles. Match `currentColor` so they theme automatically.
- **Common glyphs:** `message-circle` / `message-square` (chat), `calendar` / `calendar-check` (turnos), `package` / `store` (productos), `zap` (automatización), `bot` (asistente), `bell` (avisos), `qr-code` (conexión WhatsApp), `clock`, `users`, `settings`.
- **Emoji:** not used as iconography. **Unicode** chars only as tiny functional marks (`▾` select chevron, `↑/↓` trend arrows) where an icon would be overkill.
- **Logo mark:** a rounded speech bubble with three nodes (two white + one coral) — reads as both a chat bubble and a mini automation flow. Files: `assets/logo-mark.svg` (theme-aware via CSS vars) and `assets/logo-mark-color.svg` (fixed jade+coral, for favicons/external use). Wordmark is set in Sora 800 with the "ia" tinted jade: **Atend·ia**.

---

## Index / manifest

**Root**
- `styles.css` — global entry (link this one file). `@import`s everything below.
- `readme.md` — this guide.
- `SKILL.md` — Agent-Skills wrapper.

**Tokens** (`tokens/`)
- `colors.css` — palettes + light/dark semantic aliases · `typography.css` · `spacing.css` (spacing, radii, shadows, motion, z, layout) · `fonts.css` (Google Fonts @import) · `base.css` (resets + body defaults).

**Components** (`components/`) — React primitives, namespace `window.AtendiaDesignSystem_*` after the bundle compiles.
- `core/` — **Button, IconButton, Badge, Avatar**
- `forms/` — **Input, Select, Switch, Checkbox**
- `surfaces/` — **Card, StatCard**
- `feedback/` — **Alert, Tabs**

**Foundation cards** (`guidelines/`) — specimen HTML shown in the Design System tab (Colors, Type, Spacing, Brand).

**UI kits** (`ui_kits/`)
- `website/` — Atendia marketing site (hero, features, how-it-works, pricing, client carousel).
- `dashboard/` — client dashboard (turnos, productos, conversaciones, settings) — responsive, light + dark.

**Assets** (`assets/`) — `logo-mark.svg`, `logo-mark-color.svg`.

---

## Caveats / open questions

1. **Brand name & logo are placeholders.** "Atendia" and the speech-bubble mark are proposals.
2. **Fonts are Google-Font substitutes** (Sora, Plus Jakarta Sans, JetBrains Mono). If you have licensed brand fonts, drop them in `assets/fonts/` and swap the `@import` in `tokens/fonts.css` for `@font-face`.
3. **Icons default to Lucide** (CDN) — flag if the product uses another set.
4. **No source design/code** was provided, so the UI kits are an informed first proposal, not a recreation. Attach a codebase/Figma to align.

**👉 Tell me: do you like the name + jade/coral direction, or want me to explore alternatives? And do you have a logo, fonts, or existing screens I should match?**
