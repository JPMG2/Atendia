# Prompt de construcción — Dashboard de clientes de Atendia

> **Cómo usar este archivo:** pegá primero `00-fundamentos.md` (marca, colores, tipografía, tokens, `tailwind.config`), y luego este prompt. Pensado para **Laravel + Livewire + TailwindCSS** (Livewire para el estado reactivo de cada vista; Alpine.js para UI liviana: drawer, toggle de tema, dropdowns). Si usás otro stack, los fundamentos no cambian.

Referencia viva: el UI kit `ui_kits/dashboard/index.html` de este design system ya implementa todo esto — usalo como fuente visual de verdad.

---

## Objetivo

El **panel de control del cliente**: donde cualquier negocio (clínica, comercio, profesional) configura cómo Atendia atiende por él y supervisa lo que pasó. Debe ser **100% responsive** (el dueño lo abre desde el celular) y soportar **modo claro y oscuro**. Claridad y baja densidad por sobre todo — el usuario puede no ser técnico.

---

## Shell de la aplicación

Layout de dos zonas: **sidebar fija** (264px) + área de contenido con **topbar sticky** (64px) arriba.

### Sidebar
- Fondo `surface-card`, borde derecho `border-subtle`, alto completo, sticky.
- Arriba: logo (marca 30px + wordmark **Atend·ia**).
- Sección "Menú" (eyebrow diminuto) con items: **Inicio** (`layout-dashboard`), **Conversaciones** (`message-circle`, badge 3), **Agenda** (`calendar`, badge 12), **Productos** (`package`), **Métricas** (`bar-chart-3`).
- Item activo: fondo `brand-soft`, texto `brand-soft-text`, peso 700; badge relleno jade. Hover en inactivos: `surface-sunken`.
- Abajo (pegado): **Ajustes** (`settings`), **Ayuda** (`life-buoy`), y una **tarjeta de upsell** `brand-soft`: icono `zap` + "Plan Inicial" + "Quedan 9 días de prueba." + botón primario `sm` *"Mejorar plan"*.
- **Mobile (≤860px):** se vuelve **drawer** que entra desde la izquierda (`transform: translateX(-100%)` → `0`), con scrim oscuro detrás. Se abre con la hamburguesa de la topbar.

### Topbar
- Sticky, fondo translúcido + blur, borde inferior.
- Izquierda: hamburguesa (solo mobile) + **buscador** (`surface-sunken`, icono `search`, placeholder *"Buscar conversación, turno, producto…"*, ancho ~320px).
- Derecha: **pill de estado** verde *"WhatsApp Conectado"* (dot success) + toggle de tema + campana con dot de notificación coral + avatar con nombre del negocio y del usuario.
- **Mobile:** ocultar pill y bloque de usuario; buscador a ancho completo.

---

## Vistas

### 1. Inicio (overview) — vista por defecto
- **PageHead:** *"Hola, María 👋"* (el saludo puede llevar el emoji del usuario, es contenido, no UI) + subtítulo *"Esto pasó hoy en Clínica Vida."* + acciones: secundario *"Exportar"* (`download`), primario *"Nuevo turno"* (`plus`).
- **Fila de 4 StatCards:** Turnos hoy `24` ▲+12% · Conversaciones `58` ▲+9 (info) · Sin responder `3` ▼+2 (warning) · Respuesta media `2s` → estable (accent). Cada uno con icono en cuadro tintado y delta con flecha/color de tendencia.
- **Grid 2 columnas (1.5fr / 1fr):**
  - **Agenda de hoy:** card con header (título + "Ver todo") y lista de turnos: hora mono + nombre + estudio + badge de estado (confirmado=success / pendiente=warning, con dot).
  - **Conversaciones:** card con header + badge "X sin leer"; lista de chats: avatar (dot online si sin leer) + nombre + último mensaje truncado + tiempo relativo.
- **Responsive:** stats 2-up ≤1100px y 1-up ≤600px; el grid 2-col colapsa a 1.

### 2. Conversaciones — inbox estilo WhatsApp
- **PageHead:** *"Conversaciones"* + *"Lo que tu asistente respondió por vos. Intervení cuando quieras."*
- Card con grid **320px / 1fr**, alto ~560px:
  - **Lista** (scroll): cada item = avatar + nombre + último mensaje + hora + contador de no leídos (pill jade). Activo: fondo `brand-soft`.
  - **Hilo:** header con avatar + estado + **pill "Auto activo"** (`bot`, jade) que indica que el asistente responde. Cuerpo sobre `chat-canvas` con burbujas; las del asistente llevan etiqueta *"Asistente"* (`bot`) arriba. Composer abajo: input redondeado (pill) + botón enviar circular jade — *"Escribí para tomar el control…"* (cuando el dueño escribe, pausa el auto).
- **Mobile (≤860px):** colapsa a solo la lista (el hilo se abriría como pantalla aparte / push).
- **Estado:** en Livewire, `selectedConversationId` + colección de conversaciones; al cambiar, recargar el hilo.

### 3. Agenda — configuración de turnos
- **PageHead:** *"Agenda y turnos"* + *"Definí cómo y cuándo Atendia agenda por vos."* + primario *"Guardar cambios"* (`check`).
- **Alert brand** explicando que con la agenda activa el asistente ofrece huecos y confirma solo.
- **Grid 1.3fr / 1fr:**
  - **Días de atención:** card con 7 filas (Lun–Dom), cada una con Checkbox + rango horario mono (los días activos resaltan con `surface-sunken`; inactivos dicen "Cerrado").
  - Columna derecha (apilada): card **Capacidad** (Select "Duración por turno" 15/30/45/60min, Input "Turnos en paralelo", Input "Anticipación mínima" con icono `clock`) + card **Automatización** (Switch *"Agendar automáticamente"*, Switch *"Enviar recordatorio 24h antes"*).
- **Responsive:** grid → 1 columna ≤1100px.

> **Nota de rubro:** para un comercio en vez de una clínica, esta misma vista cambia el vocabulario (p. ej. "Horarios de atención" / "Retiro de pedidos") pero la estructura es idéntica. El sistema es rubro-agnóstico por configuración, no por código duplicado.

### 4. Productos — catálogo
- **PageHead:** *"Productos y servicios"* + *"Lo que tu asistente puede ofrecer y cotizar."* + secundario *"Importar"* (`upload`) + primario *"Agregar"* (`plus`).
- **Grid de cards 3×N** (`Card interactive`): icono en cuadro `brand-soft` + badge Activo(success)/Oculto(neutral) + nombre `lg` + tipo (Servicio/Estudio/Paquete) + precio en Sora 700 2xl + icono `pencil` para editar.
- Ejemplos (clínica): Consulta cardiología $18.000 · Electrocardiograma $12.500 · Holter 24h $24.000 · Ecodoppler $28.500 (oculto) · Ergometría $26.000 · Chequeo integral $45.000.
- **Responsive:** 2-up ≤1100px, 1-up ≤600px.

### 5–7. Placeholders (diseñar a pedido)
**Métricas**, **Ajustes**, **Ayuda** — por ahora estado vacío centrado con icono + texto *"Esta vista es un placeholder. Pedímela y la diseñamos completa."* Cuando estés listo, pedímelas (Métricas: gráficos de conversaciones/turnos en el tiempo; Ajustes: perfil del negocio, conexión de WhatsApp por **QR** con Evolution API, mensajes automáticos, equipo).

---

## Comportamiento responsive (resumen)
- **≤1100px:** grids de contenido a 1 columna, stats 2-up, productos 2-up.
- **≤860px:** sidebar → drawer con hamburguesa; inbox → solo lista.
- **≤600px:** todo 1 columna; topbar condensada (sin pill ni usuario); buscador full-width.

## Tema claro/oscuro
Toggle en topbar, persistir en `localStorage`, clase `.dark` en `<html>`. Todo desde aliases semánticos.

## Notas técnicas (Laravel + Livewire)
- Cada vista = un **componente Livewire** (`Dashboard\Overview`, `Dashboard\Conversations`, `Dashboard\Agenda`, `Dashboard\Products`). El shell (sidebar + topbar) es un layout Blade que envuelve `{{ $slot }}`.
- Navegación entre vistas: rutas reales o `wire:navigate` (SPA-like) para que el shell no recargue.
- Drawer, toggle de tema, dropdowns: **Alpine.js**.
- Conexión de WhatsApp: la pill de estado refleja el estado real de la instancia de **Evolution API**; la pantalla de conexión muestra el **QR** que devuelve la API.
- Inbox: el flujo entrante real llega vía **n8n → webhook Laravel**; las respuestas del asistente se marcan visualmente como "Asistente" para distinguir de la intervención humana.
- Iconos Lucide; mantené componentes chicos y bien factorizados.
- No inventar vistas ni datos de relleno innecesarios; los placeholders se diseñan cuando los pidas.
```
```
