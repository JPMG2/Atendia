# Prompt de construcción — Sitio web oficial de Atendia

> **Cómo usar este archivo:** pegá primero `00-fundamentos.md` (marca, colores, tipografía, tokens, `tailwind.config`), y luego este prompt. Está escrito para construirse en **Laravel + Blade + TailwindCSS** (con Alpine.js para interacciones livianas como el toggle de tema y el menú móvil). Si usás otro stack, los fundamentos no cambian.

---

## Objetivo

Una **landing de marketing moderna y de alto impacto** que explique qué hace Atendia y convierta. Público amplio: desde un cardiólogo hasta una vendedora de golosinas, así que debe ser **clara, confiable y cálida** — impactante sin ser técnica. Debe ser **100% responsive** y soportar **modo claro y oscuro**.

Referencia viva: el UI kit `ui_kits/website/index.html` de este design system ya implementa todo esto — usalo como fuente visual de verdad.

---

## Estructura de la página (en orden)

### 1. Navbar (sticky, frosted)
- Fija arriba, fondo translúcido con `backdrop-filter: blur(14px)` sobre `surface-page`, borde inferior `border-subtle`.
- Izquierda: logo (marca + wordmark **Atend·ia**). Centro: links *Cómo funciona · Funciones · Casos · Precios* (sentence case). Derecha: botón toggle de tema (icono `moon`/`sun`), botón ghost *"Ingresar"*, botón primario *"Empezar gratis"*.
- **Mobile:** ocultar links y el grupo de CTAs; mostrar hamburguesa que abre un drawer.

### 2. Hero
- Grid 2 columnas (texto / mock). Colapsa a 1 columna ≤900px.
- **Izquierda:** badge jade con dot *"Atiende por vos en WhatsApp"* → `h1` 6xl: **"Tu negocio,** *atendido por IA*" (segunda línea en `brand`) → párrafo `lg` `text-muted` → fila de CTAs: primario `lg` con **brand glow** + icono `zap` *"Crear mi asistente"*, secundario `lg` con icono `play` *"Ver cómo funciona"* → fila de checks (`check` jade): *"14 días gratis · Sin tarjeta · Para cualquier rubro"*.
- **Derecha:** **mock de teléfono** mostrando una conversación de WhatsApp real (header jade con avatar `bot` + "en línea", burbujas in/out con tokens `bubble-*`). Glow radial jade detrás. Ejemplo de diálogo: paciente pide turno → asistente ofrece 2 horarios → confirma + recordatorio.

### 3. Franja de social proof
- Texto centrado `text-subtle`: *"Negocios de todos los rubros ya atienden con Atendia"* + fila de nombres de clientes en Sora 700 apagado (Clínica Vida, Kiosco Sol, Estudio Lex, Dra. Ríos, Pastelería Mía, AutoFix).

### 4. Funciones (grid 3×2)
Cards (`Card interactive`, hover lift + borde jade). Cada una: icono en cuadro `brand-soft` 46px + título `xl` + cuerpo `sm` `text-muted`.
1. **Agenda turnos sola** (`calendar-check`) — días, horarios, capacidad; ofrece huecos y confirma.
2. **Muestra tu catálogo** (`package`) — productos con precio y foto; responde y manda link.
3. **Responde 24/7** (`message-circle`) — consultas frecuentes en segundos, en tu tono.
4. **Te avisa lo importante** (`bell`) — resumen de turnos y chats; intervenís solo si hace falta.
5. **Vos tenés el control** (`sliders-horizontal`) — todo desde un panel claro.
6. **Tu número, tu marca** (`shield-check`) — sobre tu propio WhatsApp.

Encabezado de sección: eyebrow *"Funciones"* + `h2` 4xl *"Todo lo que tu negocio necesita para atender mejor"* + subtítulo.

### 5. Cómo funciona (panel con 4 pasos)
- Contenedor card grande (radio 28px, padding 48px). Eyebrow *"Cómo funciona"* + `h2` *"De la pregunta a la respuesta, automático"*.
- 4 pasos en fila (colapsa a 2×2 ≤900px), cada uno con número mono (`01`–`04`), icono en cuadro (el paso 3 usa `accent-soft`/`accent` para romper el ritmo), título `lg`, cuerpo `sm`:
  1. **El cliente escribe** (`message-square`)
  2. **Atendia lo procesa** (`workflow`) — n8n entiende y busca en tu config
  3. **Responde por vos** (`sparkles`)
  4. **Vos supervisás** (`layout-dashboard`)

### 6. Casos reales (2 columnas)
Dos cards grandes mostrando la dualidad del producto:
- **Profesionales y clínicas** (jade, icono `stethoscope`): *"Dr. Luis Paz · Cardiología"* — define estudios, duración, capacidad; agenda, recuerda, reprograma. Tags: Turnos · Recordatorios · Estudios.
- **Comercios y emprendedoras** (coral, icono `candy`): *"Pastelería Mía"* — sube catálogo con precios; responde por sabores, toma pedidos. Tags: Catálogo · Pedidos · Horarios.

### 7. Precios (3 planes)
Cards de igual altura; el del medio destacado (borde jade 2px, sombra `lg`, badge *"Más elegido"*).
- **Inicial — $0 / 14 días:** 1 número, respuestas automáticas, agenda o catálogo, panel. CTA secundario *"Empezar gratis"*.
- **Negocio — $29 / mes** (destacado): todo lo anterior + turnos y catálogo juntos, recordatorios, reportes, soporte prioritario. CTA primario *"Crear mi asistente"*.
- **Pro — $79 / mes:** + varios números, roles de equipo, integraciones a medida. CTA secundario *"Hablar con ventas"*.
- Precio en Sora 800 5xl; cada feature con check jade.

### 8. Carrusel de clientes ⭐ (pieza distintiva)
- **Marquee de doble fila** que se desliza infinito en direcciones opuestas (fila 1 →, fila 2 ←, velocidades distintas ~46s / ~54s). **Pausa al hover.** Respeta `prefers-reduced-motion` (sin animación).
- Máscara de degradado en los bordes laterales (fade a transparente) para que entren/salgan suave.
- Cada tarjeta (~332px): avatar con iniciales auto-tintadas + nombre + rubro + 5 estrellas coral + cita corta entre comillas.
- 8 testimonios variados de rubro (clínica, repostería, cardiología, kiosco, abogados, taller, odontología, estética). Encabezado: eyebrow *"Clientes"* + `h2` *"Negocios que ya no atienden solos"*.

### 9. CTA de cierre
- Banner ancho jade-700 con glow radial coral en una esquina. `h2` 5xl blanco *"Tu próximo cliente está escribiendo ahora"* + párrafo + CTA acento *"Empezar gratis"* (icono `zap`) + CTA secundario translúcido *"Agendar demo"*.

### 10. Footer
- Grid: columna de marca (logo + descripción) + 3 columnas de links (Producto / Recursos / Empresa). Barra inferior: *"© 2026 Atendia. Hecho para los que atienden."* + Términos · Privacidad.

---

## Comportamiento responsive
- **≤900px:** hero 1 columna; funciones y precios 2-up; pasos 2×2; casos y footer apilados; links de nav ocultos.
- **≤560px:** todo 1 columna; CTAs de nav ocultos (quedan en el drawer).

## Tema claro/oscuro
- Toggle en navbar. Persistir elección en `localStorage`. Aplicar con clase `.dark` en `<html>`. Todo sale de aliases semánticos → no hardcodear colores.

## Tono de copy
Español rioplatense, sentence case, cálido y concreto, sin jerga, sin emoji en la UI. Beneficio del usuario al frente.

## Notas técnicas (Laravel/Blade)
- Cada sección = un componente Blade (`<x-site.hero />`, `<x-site.pricing />`, etc.) para mantener archivos chicos.
- Toggle de tema y drawer móvil con **Alpine.js** (`x-data`, `x-show`, `@click`).
- Iconos: Lucide vía `lucide` (CDN o `@lucide/lab`), renderizar con `data-lucide` + `lucide.createIcons()`.
- Marquee: CSS `@keyframes` con `translateX(-50%)` sobre la lista duplicada (`[...items, ...items]`).
- No inventar secciones nuevas sin pedírmelo; si una sección queda vacía, es problema de layout, no de relleno.
```
```
