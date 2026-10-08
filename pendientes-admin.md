# 🛠️ Panel Admin — pendientes

> Guía CORTA y propia del panel admin, aparte de `aproduccion.md` (go-live) y de
> `hallazgos.md` (defectos encontrados de paso). Se tacha a medida que se cierra.
> Un punto cerrado NO se borra: queda tachado con la fecha, para no re-discutirlo.
> Última revisión: 2026-10-08, al cerrar el día (Modelos de IA, lo contable de Consumo de IA,
> Soporte v2 con sus 3 mejoras y la migración de embeddings, todo hecho y pusheado; el bloque G
> tiene lo que quedó abierto de esa jornada, y casi todo es un dato o una decisión de ella).
> Antes: 2026-10-07
> (bloque A y bloque E verificados contra el código: 25 casillas estaban hechas y seguían abiertas).

## Cómo se cierra un punto de esta lista

Lo que salió mal en el panel cliente fue cerrar pantallas sin estas cuatro cosas.
Acá cada punto se cierra con las cuatro, o no se cierra:

1. **Dato real, no maqueta.** Si la pantalla muestra un número, ese número sale de la
   base y es verdad. Una cifra de adorno es un defecto, no un avance.
2. **Nada mudo.** Ningún tile, botón ni ítem de menú se dibuja sin su destino
   (`controles-vivos.md`). Si la capacidad no existe todavía, no se dibuja el control.
3. **Los candados del panel admin ya existen** (cerrados el 2026-10-03): pestaña con nombre ·
   render con la base VACÍA · 390px y 900px con filas reales · ítem de menú sin destino.
   Ojo: el de 390/900px mide el desborde de la PÁGINA, no el de una tabla dentro de su card.
4. **Verificación visual real** (captura o browser test) + suite de browser antes del commit.

Lo que YA cubre al admin sin tocar nada: los 19 `check-*.sh` que `run-checks.sh` corre sobre
CUALQUIER archivo escrito (blade, queries, comentarios, controles vivos, avisos nativos,
validación en Form, fechas, tenancy, orden de campos, filas de formulario, planes, reportes,
getters, migraciones, correo) y la skill `atendiadesign`, que `require-design-skill.sh` exige
para tocar cualquier archivo de `resources/views/`. Única excepción a propósito:
`check-upload-moderation-golden-rules.sh` se saltea `app/Livewire/Forms/Admin/*` y
`Configuration/*` — la moderación es para lo que sube un NEGOCIO, no para lo que carga ella.

**Cerrado el 2026-10-03, antes de la primera pantalla:**
- La skill `atendiadesign` tenía CERO líneas sobre una pantalla de administración (su
  checklist de salida se titulaba "formularios y componentes Livewire"), así que el admin
  se iba a construir con la vara de un formulario. Ahora tiene su **§7 "Pantalla de
  administración"** con reglas propias: falta ≠ cero, sello de frescura, todo tile es una
  puerta, la tabla es la pantalla, vacío de verdad, voz operativa, y su propio checklist.
  Y la skill dejaba de mentir: decía que el stack tenía n8n (está descartado) y escribía
  "Atendia" en lugar de "AtendIa".
- `check-panel-screen.sh` (capa C, nuevo): al escribir una vista del admin exige **en el
  acto** que ya tenga su ruta en `routes/admin.php` y su ítem en `MenuSeeder.php`. Probado
  contra las 8 pantallas que ya existen (ninguna falsa alarma) y contra una nueva
  incompleta (atrapa las dos faltas). Allowlist: `ws-demo`, con su razón escrita.
- `catches.tsv` ahora registra también las atrapadas de los **guardianes Pest**, no solo de
  los hooks, y `catches.sh` avisa cuántos días de historia real tiene el registro: sin eso,
  "0 atrapadas en 60 días" se leía como evidencia cuando era el primer día, y se iban a
  borrar 17 candados sanos.

---

## A · Lo que se puede construir ya (no depende de nadie)

### A1. El Inicio del admin de verdad — HECHO 2026-10-03 (falta 1 número)
Era `resources/views/admin/dashboard.blade.php`, una rejilla de 6 tiles con 4 que no iban a
ningún lado. Ahora es `components/admin/home/⚡index.blade.php` (la convención de carpeta por
opción de menú), con las queries en `Subscription` y `Payment`, no en el Blade.

- [x] ~~Comprobantes por verificar~~ → tile que lleva a `/admin/pagos`
- [x] ~~Renovaciones de la semana, con monto~~ → tabla con negocio, plan, ciclo, fecha y monto
- [x] ~~Vencidos / pausados~~ → tabla "Vencidos y pausados", con en gracia vs. pausado
- [x] ~~MRR~~ → anual prorrateado a 12; cuenta activos y en gracia, no las pruebas, y dice
      de cuántas suscripciones sale
- [x] ~~Subidas de plan por verificar~~ → comprobante pendiente cuyo plan ≠ el de la suscripción
- [x] ~~Los 4 tiles muertos~~ → la rejilla entera se fue; sus áreas ya viven en el menú
- [x] ~~**Bajas programadas (plan, monto y fecha)**~~ — HECHO 2026-10-03, con A6.
      Tile en el Inicio con el monto mensual que se va, y tabla con negocio, plan, monto,
      fecha de fin y fecha en que la pidió. **Los 6 números del Inicio ya existen.**
      Decisión de ella y diseño que salió de ella:
      Decisión de ella (2026-10-03): la baja son dos momentos, cuándo la pide y cuándo
      termina; y **si ya pagó, el asistente sigue andando hasta la fecha real de baja**.
      Diseño que sale de eso:
      - UNA columna, `subscriptions.canceled_at` = cuándo la pidió.
      - El fin efectivo es `current_period_ends_at`, que YA existe. No se duplica.
      - Todo lo demás se DERIVA, sin estado nuevo y sin job: está dada de baja y todavía
        anda si `canceled_at` tiene valor y el período no venció; terminó cuando venció.
        Es el mismo patrón que ya usa la prueba vencida ("this row never needs a
        downgrade job").
      - Al vencer una baja NO hay gracia ni pausa: termina y punto.
      - Se registra desde la ficha del negocio (A6), con diálogo de confirmación.
      - **Toda la política vive en `App\Actions\Billing\CancelSubscription`** — hoy: corre
        hasta el fin del período pagado, sin devolución y sin preaviso. Si el abogado o el
        contador lo cambian, es ESE archivo y ninguno más.
      - [ ] Pendiente de §B: la baja por autogestión del negocio. No puede existir antes que
            el pago por autogestión.

### A2. Ajustes de plataforma — PANTALLA HECHA 2026-10-05
`/admin/ajustes`, bajo Plataforma. **La fila PISA al `config` en el arranque**: los ~15
llamadores que ya leen `config('atendia.…')` no se tocaron, `config/atendia.php` queda como
el default escrito, y lo que ella edita manda. Agrupado por TAREA y no por archivo de config,
cada ajuste dice qué pasa si lo movés, con su default al lado y un botón para volver.
- [x] ~~Umbral de charla terminada~~ (`analysis.idle_hours`, 1–48 h).
- [x] ~~Horas locales de los automáticos~~: cumpleaños, resumen del día, avisos de pago,
      resumen semanal (hora Y día) y recordatorio de turno.
- [x] ~~Derivación~~ (`handoff.reminder_minutes`, `handoff.customer_idle_hours`) y
      ~~referidos~~ (`referral.invited_trial_days`, `referral.founders`) + días de gracia.
- [ ] **Umbrales de similitud** (`same_intent_similarity`, `catalog_similarity`,
      `same_question_similarity`…): NO entraron. Son vectores medidos sobre datos reales, no
      preferencias; moverlos a ciegas rompe el análisis sin que se note. Van cuando haya una
      pantalla que muestre el efecto de moverlos, no un campo suelto.
- [ ] Cómo elige el sistema a qué cliente va la respuesta de la dueña desde su WhatsApp
      (todavía no hay un valor configurable: primero hay que decidir la regla).
- [ ] Cumpleaños solo con opt-in del cliente (hoy fijo en sí) — es una COLUMNA de negocio,
      no un ajuste de plataforma.
- [x] ~~Al sumar un ajuste nuevo: anotarlo acá~~ — hoy se suma una fila al `PlatformSettingSeeder`
      y aparece sola en la pantalla, agrupada y validada por su tipo.
- Dos ajustes nuevos el 2026-10-07, del escritorio de incidencias (A7):
  `incidents.unanswered_minutes` (15 min, cuándo una charla sin respuesta cuenta como falla) y
  `incidents.digest_time` (20:00, cuándo le llega el correo del día).
- Tres ajustes nuevos el 2026-10-08: `support.overdue_hours` (24 h, cuándo un reporte sin respuesta se
  pinta de rojo en Soporte), `support.overdue_hours_high` (8 h, lo mismo para los de prioridad alta)
  y `costs.fixed_monthly_usd` (0 = "no cargado"; con valor, Consumo de IA muestra el resultado neto).
- **Fuera a propósito**: `referral.reward_percent`. Nada en `app/` lo paga (ver `hallazgos.md`):
  sería un control que no hace nada.

### A3. Consumo de IA por cliente — HECHO 2026-10-04
Backend hecho (`ai_usages` + listener `RecordAiUsage` + `atendia:ai-costs`).
- [x] ~~Pantalla~~: `/admin/consumo-ia` bajo `Cobros`. Volumen por negocio (llamadas, hilos,
      mensajes, audio), tokens de entrada/cacheada/salida, costo del mes contra el anterior y
      desglose por tipo de llamada. Selector de los últimos 12 meses.
- [x] ~~Las tarifas de `.env`~~: ya no hacen falta para el costo de los tokens. El precio sale
      de `ai_models`, vigente el día de la llamada; `.env` solo conserva embeddings y audio,
      que tienen default. Una llamada sin precio publicado NO cuenta como cero: la cifra dice
      cuántas llamadas quedaron afuera.
- [x] ~~Un solo cálculo~~: `App\Classes\Main\AiSpend` lo resuelve para la pantalla y para el
      comando, leyendo el libro de precios UNA vez (antes era una consulta por fila).
- [x] ~~Las 3 mejoras ofrecidas~~ (2026-10-04, elegidas por ella): **alerta contra el plan**
      (chip rojo cuando el costo supera `atendia.ai_alert_share` del precio de su plan),
      **costo por conversación** en cada fila, y **exportar el mes** por la capa de reportes
      (`AiSpendReport`, Imprimir/Excel/CSV, con el mes en la URL).
- [x] ~~La fila lleva a la ficha~~ (2026-10-04), igual que el Inicio y Soporte.
- [x] ~~**Lo contable, definido** (2026-10-08, pedido de ella)~~: **Ingreso** = la regla del MRR (activos y
      en gracia, el anual ÷ 12, las pruebas no, los demos afuera). **Costo** = IA medida, valuada
      al precio de ese mes, con el audio incluido (el total del mes lo dejaba afuera y no cerraba
      con las filas). **Margen sobre la IA** = ingreso − costo de TODA la IA, pruebas y demos
      incluidos. Un negocio que paga y cuesta más de lo que paga sale con "Pierde $X"; uno en
      prueba dice "En prueba" (su costo es el de ganarlo, no una pérdida). Un mes anterior lee su
      ingreso de la foto de `revenue_snapshots`; sin foto no hay margen, nunca un ingreso cero.
      Es margen sobre la IA: servidor, WhatsApp y demás costos fijos no se miden en la app.
- [x] ~~**Costos fijos del mes** como ajuste de plataforma~~ (2026-10-08): `costs.fixed_monthly_usd`,
      en Ajustes → Costos fijos. Con valor, Consumo de IA muestra el resultado neto; en 0 dice
      "no cargado". Lo que falta es el DATO de ella (ver G).
- [x] ~~Rehecha entera el 2026-10-05~~ (ver E14 y E14-bis): era legible como dato y
      ilegible como pantalla.
- [ ] **El minutaje de audio perdió su columna** al pasar a tabla: nueve columnas no entran a
      1280px sin cortar. Sigue dentro del costo (`AiSpend` lo valoriza) pero **NO está en los
      tres exportables**: `AiSpendReport` tiene 10 columnas y ninguna es audio (corregido el
      2026-10-07; antes esta línea afirmaba que sí salía). Hoy el dato no se ve en ninguna
      parte: si lo querés en pantalla hay que sacarle el lugar a otra columna, y en los
      exportables entra sin pelear por ancho.

### A4. Usuarios y accesos — HECHO 2026-10-05
`/admin/usuarios`, bajo Plataforma.

**Lo entregué mal la primera vez y ella lo frenó.** Armé la pantalla desde la TABLA `users`,
así que listaba también a los dueños de negocio: la pantalla decía que sus clientes eran
personal de la plataforma. Un cliente es un cliente y vive en **Negocios**.

> **Definición (de ella, 2026-10-05):** usuario = cuenta creada DESDE el dashboard admin.
> El único que viene del seeder es `admin@admin.com`; **cualquier otro se crea en el sistema**.

- [x] ~~La lista son las cuentas con `access-admin-panel`~~, leído del PERMISO y no de una lista
      de roles: un rol nuevo entra solo con que se le dé el permiso.
- [x] ~~Alta desde la pantalla~~: nombre, correo y rol. No se elige contraseña — la persona
      recibe el correo de acceso y la define ella.
- [x] ~~**El super-admin NO se puede regalar desde la web**~~: el rol `admin` pasa todos los
      gates por `Gate::before`, así que el selector no lo ofrece Y la regla lo rechaza aunque
      lo manden a mano. Lo que la web reparte es el rol `support`: el panel, sin super-admin.
- [x] ~~Un permiso gobierna quién puede crear usuarios~~ (`manage-admin-users`), y la cerradura
      está en la ACCIÓN, no en ocultar el botón.
- [x] ~~Reenviar el acceso~~ desde la fila, solo a quien no verificó (reenviárselo a quien ya
      verificó es un correo que confunde).
- [x] ~~El último acceso es el de verdad, y dice desde dónde~~: sale de `login_activities`
      (las sesiones viven en Redis, esa tabla está vacía).
- [x] ~~**Hallazgo del primer uso**: `admin@admin.com` figuraba SIN VERIFICAR.~~ Lo encontró la
      pantalla apenas abrió y ella lo resolvió con el botón de la fila (2026-10-05 18:04). El
      circuito entero quedó probado de punta a punta: botón → cola → correo → link.
- **Movido**: suplantar a un usuario ("ver el panel como esta persona") se pensó para ayudar a
  un CLIENTE que no puede entrar, así que pertenece a la ficha del Negocio, no acá.

### A5. Seguridad — roles, permisos y auditoría
**Parte 1 (cerraduras) HECHA 2026-10-05.** Hasta hoy TODA ruta admin pedía solo
`access-admin-panel`: dejar entrar a alguien de soporte era darle la plataforma entera.
Ahora cada área tiene su llave, y la misma llave va en la ruta Y en el ítem de menú.
- [x] ~~16 permisos por área~~ (`businesses.view`, `payments.view/verify`, `support.view`,
      `moderation.view`, `ai.view/manage`, `catalogs.manage`, `company.manage`,
      `settings.manage`, `users.view`, `logs.view`, `adoption.view`, `integrations.view`,
      `testimonials.moderate`, `businesses.manage`).
- [x] ~~Las 15 rutas admin exigen el suyo~~, y un guardián falla si mañana alguien suma una
      pantalla sin permiso propio (`AdminAreaPermissionsTest`).
- [x] ~~El menú lleva el mismo permiso que la ruta~~: no se ofrece una puerta que da 403.
- [x] ~~`support` quedó con lo mínimo~~ (su ejemplo): trabaja reclamos y mira el negocio
      detrás de uno. Sin catálogos, sin compañía, sin ajustes. Probado con 403 en cada uno.
- [x] ~~**DEFECTO del menú que esto destapó**~~: un GRUPO sin ruta propia seguía dibujándose
      aunque los permisos le vaciaran todos los hijos — "Plataforma" quedaba en el menú de
      soporte sin poder abrir nada. `Menu::filterByPermission` ahora lo descarta.

**Parte 2 (pantalla de Roles) HECHA 2026-10-05.** `/admin/roles`, con permiso `roles.manage`.
Un rol nuevo nace acá: los que vienen — diseño, call center, programación — ya no necesitan
tocar el seeder.
- [x] ~~Matriz rol × permiso agrupada por ÁREA~~, y cada permiso dice QUÉ habilita
      ("Condiciones fiscales", no `catalog.tax-condition`). Nadie necesita saber la clave.
- [x] ~~`admin` protegido de verdad~~: no se lista para editar (`Access::staffRole()` nunca lo
      devuelve), no se guarda y no se borra. Probado por los tres lados.
- [x] ~~`access-admin-panel` es implícito~~, no una casilla: un rol sin eso serían llaves de un
      edificio al que nadie puede entrar.
- [x] ~~Un rol que alguien tiene no se borra~~ (dejaría gente afuera); uno que no tiene nadie sí,
      con diálogo de confirmación.
- [x] ~~Guardar invalida el caché de spatie~~: sin eso un permiso recién sacado sigue dejando
      pasar hasta que caduque.
- [x] ~~**El rastro de quién reparte las llaves** (2026-10-05)~~. El resto de la auditoría va
      con `LogsActivity` sobre un modelo, y eso NO podía ver esto: darle un permiso a un rol
      escribe una fila PIVOTE y no dispara evento de modelo. Sin esto el log decía quién editó
      un catálogo pero no quién le dio el panel a alguien. Ahora `events_enabled` de spatie +
      `RecordAccessChange` escriben en `activity_log` con `log_name = access`: qué se dio o se
      quitó, a quién, y quién lo hizo. Guarda NOMBRES, no ids — un id no le dice nada a quien
      lea esto en un año, y apunta a una fila que puede haberse renombrado.
- [x] ~~**Pantalla de auditoría** (2026-10-05)~~: `/admin/auditoria`, permiso `audit.view`.
      El filtro es la PERSONA, porque la pregunta es "qué hizo Rocío", nunca "qué pasó en
      `businesses`"; y "El sistema" es una opción propia porque **757 de los 848 registros no
      tienen persona detrás** (son sugerencias que archiva la IA). Abre en lo fuerte — bajas,
      restauraciones y cambios de acceso —, que es lo que el ruido tapaba.
      Arreglado de paso: un negocio eliminado salía como "Negocio #12" (spatie tenía
      `include_soft_deleted_subjects` apagado), y con el filtro en "solo lo fuerte" se pintaban
      TODAS las filas, que es no destacar nada.

### A5-bis. Logs del sistema, para su norte — HECHO 2026-10-05
Ella preguntó si debía ser tabla; le dije que NO, con razones: una entrada es un bloque con
traza, una tabla la trunca o copia basura separada por tabulaciones, y su norte es **copiar y
pegar el problema**. Así lo hacen Sentry, Telescope y Papertrail: lista colapsable con bloque
crudo y acción de copiar. Lo que faltaba no era la forma, era el contenido:
- [x] ~~El copiado lleva CONTEXTO~~: app, entorno, PHP, Laravel, archivo y cuándo se copió.
      Pegada sola, una entrada abre una ronda de preguntas; con esto se contesta de una.
      Nunca adivina: solo lo que el proceso sabe de verdad.
- [x] ~~Repetidos agrupados~~: el mismo error 40 veces es UNA línea que dice 40, con la hora
      del primero. Se conserva la traza del más nuevo.
- [x] ~~"Copiar todo"~~: contexto + todas las entradas, para cuando el problema es una
      secuencia y no una entrada.
- [x] ~~Bug que introduje y cacé con un test viejo~~: ordenar por timestamp barajaba todo lo
      escrito dentro del mismo segundo. El agrupado ya conserva el orden de aparición.

### A6. Módulo "Negocios" — HECHO 2026-10-03
`/admin/negocios`: la lista con plan, estado, vencimiento y deuda, y la ficha de cada uno
con su plan, próximo cobro, lo que debe, sus últimos 10 pagos y la baja.
- [x] ~~Pantalla de negocios (ficha, plan, estado, suspensión)~~
- [x] La query vive en `Business::directory()` y `Business::ledger()`, nunca en el Blade.
- [x] ~~Filtro por estado~~ (2026-10-04): combobox en la pantalla y `?estado=` en la URL, para
      que un tile del Inicio aterrice en SU cola. `problem` agrupa vencidos y pausados, que es
      el par que el Inicio muestra junto.
- [x] ~~Buscador por nombre~~ (2026-10-04): va a la base con `whereTextMatches`, así que
      "Odontologico" encuentra "Odontológico"; `?buscar=` en la URL y "ningún negocio coincide"
      dicho distinto de "todavía no hay negocios".

### A7. Aviso diario de charlas que salieron mal — HECHO 2026-10-07
Anotado el 2026-09-24. Investigado antes de construir (Intercom, Linear, el patrón de daily
digest): el orden por llegada es operativamente **incorrecto** y un contador no sirve — sirve
la fila con la evidencia, porque cada una es una relación recuperable.

- [x] ~~Las charlas con problema del día~~ → pantalla **Qué salió mal** (`/admin/incidencias`,
      permiso `incidents.view`, bajo Negocios), ordenada por severidad y no por fecha:
      sin responder · trabajo caído · derivada sin atender · respuesta marcada mal · cliente
      molesto. Cada fila lleva negocio, cliente, cuánto espera, lo último dicho y su acción.
      Abre en la peor pestaña que tenga filas. Una charla con dos males es UNA fila, bajo su
      peor categoría (lo cacé mirando la captura: el mismo cliente salía dos veces y el
      encabezado prometía más trabajo del que había).
- [x] ~~Que el aviso le llegue sin abrir la pantalla~~ → `atendia:incidents-digest` diario a
      las 20:00 (`incidents.digest_time`, girable desde Ajustes). **En un día limpio no manda
      nada**: un correo nocturno que dice "no pasó nada" enseña a archivarlo sin leer.
- [x] ~~El patrón de fondo, no solo las filas~~ → cuando un negocio concentra 2 o más del
      mismo problema, se nombra arriba de la tabla sin sacar ninguna fila.
- [x] ~~Falta "el cliente que repite"~~ (2026-10-07): entró como quinta señal, **sin
      embeddings ni umbrales**: dos o más preguntas de la misma charla que `resolved_by`
      marcó como `nobody`. Lleva la ÚLTIMA pregunta, que son las palabras que usó cuando ya
      estaba cansado de preguntar, y pesa más que una derivación: insistir es lo que hace
      alguien justo antes de irse, y es la única señal que el cliente manda a propósito.
- [ ] El umbral de "sin responder" quedó en 15 min (`incidents.unanswered_minutes`): hay que
      mirarlo con tráfico real, no decidirlo a ojo.

### A8. Tags de la demo del hero, configurables con estacionalidad — HECHO 2026-10-06
Pedido de ella 2026-09-22. Dos maestros nuevos en el hub de Catálogos, grupo **Landing**.
- [x] ~~Tags evergreen administrables~~: tabla `demo_tags`, maestro "Ejemplos del hero".
      Los 8 rubros salieron de `lang` y hoy se editan; el bloque de `lang` quedó marcado
      como SEMILLA y nada lo renderiza. `DemoTagSeeder` usa `firstOrCreate` y no
      `updateOrCreate` justamente para no pisarle una tarde de ediciones.
- [x] ~~Variantes estacionales con ventana y prioridad~~: tabla `seasonal_windows` (el
      CUÁNDO, sin contenido propio) + filas de `demo_tags` con esa ventana. **Una variante
      es un DELTA**: lo que llena pisa, lo que deja vacío hereda, y por eso todo menos el
      slug es nullable. La resolución vive en UN método, `DemoTag::resolved($día)`.
- [x] ~~Preview por fecha~~: "Ver como si fuera" debajo de la lista, con datepicker; dice
      qué temporada manda ese día y pinta las pastillas resueltas. No cambia nada.
- **Trampas de la memoria, atendidas**: zona horaria FIJA (`atendia.seasonal_timezone`, no
      UTC ni la del visitante); prioridad explícita para solapamientos; caché solo del día
      de HOY, invalidada al guardar (una fecha de preview no se cachea); y el test que
      importa es el del FIN de la ventana — al día siguiente vuelve el evergreen entero.
- La lista distingue "En curso" de "Activa": encendida y dentro de sus fechas no son lo
      mismo, y solo la segunda es lo que el visitante está viendo.
- Falta el cajón de embeddings: una variante no reindexa nada todavía porque su contenido
      es la demo del hero, que no entra al RAG.
- [x] ~~Las 3 mejoras ofrecidas al cerrar~~, pedidas por ella el mismo día: **prender y apagar
      desde la fila** (una temporada que sale mal se apaga en un click, no entrando a editar),
      **el preview con el teléfono** (las pastillas eligen y abajo se lee la conversación que
      leería el visitante, con el nombre del negocio en el encabezado) y **"Repetir el año que
      viene"** (clona ventana y variantes con las fechas corridas 365 días, APAGADA: se escribe
      un año antes y se enciende cuando se leyó; repetir dos veces no deja dos copias).

### A9. Calificaciones de la IA — la del cliente YA ESTÁ (2026-10-07); falta la de ella
Pedido de ella 2026-09-25 ("dejalo en pendiente") y ampliado el 2026-10-03.
Los pulgares de ELLA siguen en `⚡ask-atendia.blade.php` sobre `ask_feedback` y **sin
pantalla**. Los del asistente que atiende a los clientes ya existen en la charla, sobre
`assistant_ratings`, y su 👎 llega al escritorio de incidencias.
- [x] ~~Pantalla de `ask_feedback`, pulgar abajo primero~~ (2026-10-07): **Calidad de la IA**
      (`/admin/calidad-ia`, bajo Cobros, permiso `ai.view`). Las dos IA SEPARADAS: la que
      atiende a los clientes, por negocio y con los "mal" primero, y la tuya con sus pulgares
      abajo y la pregunta que falló.
- [x] ~~**Capturar la calificación de la IA que atiende al cliente del negocio**~~
      (2026-10-07): tabla `assistant_ratings` (tenant + RLS) y los dos pulgares en la charla,
      sobre lo que escribió **la IA** nada más. El 👎 **abre la corrección** con la pregunta
      del cliente cargada y la respuesta en blanco, porque pedir sólo una calificación no
      consigue calificaciones: pedir que arreglen lo que les molestó, sí. Marcar dos veces
      corrige en vez de acumular.
- [x] ~~Decidir CÓMO se pide por WhatsApp~~ — **DECIDIDO QUE NO, 2026-10-07, freno de ella.**
      `businesses` no tiene columna de canal: hoy TODOS cuelgan de Evolution, o sea del canal
      no oficial. Un mensaje extra, idéntico y no solicitado a cada cliente es el patrón por
      el que Baileys bloquea, y el número que se quema es **el del negocio**, no el nuestro.
      Entra sólo el día que exista Cloud API, detrás de una bandera por negocio: hasta
      entonces el control no se dibuja (`controles-vivos.md`). Investigado: con la API oficial
      también haría falta plantilla aprobada pasadas las 24 h.
- [x] ~~La pantalla del admin manda sobre las dos~~ (2026-10-07, la mitad del cliente): un
      pulgar abajo aparece en **Qué salió mal** como "Respuesta marcada mal", con negocio,
      cliente y la respuesta rechazada, y se queda **7 días** — los demás son hechos que
      pasan, éste es un defecto que sigue contestando mal hasta que alguien le enseñe.
- [x] ~~Falta el otro lado y la calidad por negocio~~ (2026-10-07): **el porcentaje NUNCA
      viaja solo.** Va con la cantidad de marcas sobre las que se calculó y, por debajo de 20,
      con un aviso de que no alcanza para concluir nada. Un negocio que nadie marcó **no
      aparece** en vez de salir en 0%: inventarle un cero acusa a un asistente que puede
      estar contestando perfecto.

### A10. Radar de personas
`platform_contacts` ya junta los datos cross-negocio (sin `business_id`, capa plataforma).
Se llena en cada mensaje entrante (`ProcessIncomingWhatsAppMessage`) y en `AdoptOrphanCustomers`.
- [x] ~~Pintarlo~~ (2026-10-07): **Radar de personas** (`/admin/radar`, bajo Negocios,
      permiso `contacts.view`). Ordena por cantidad de negocios y no por fecha, porque el
      solapamiento —la misma persona escribiéndole a dos negocios— es lo ÚNICO que esta tabla
      sabe y que ningún negocio puede ver desde su panel. Cuando no hay nadie compartido lo
      dice con palabras, en vez de dejar un cero para interpretar.

### A11. Tasa de cambio automática (Bs/USD)
Pedido de ella 2026-09-23. Investigación hecha (memoria `atendia-tasa-cambio-auto`):
`ve.dolarapi.com` funciona, el BCV no tiene API (tabla + SSL incompleto).
**EN PAUSA por orden de ella (2026-10-03).**
- [ ] Cadena de fuentes: API → BCV → última tasa guardada
- [ ] Tabla de tasas con fecha y fuente + validación de cordura (variación diaria máxima)
- [ ] Aviso al admin si todas fallan o la tasa queda vieja
- [ ] **Decisión de ella pendiente:** qué hacemos si la API muere o el BCV cambia el diseño

### A12. Feriados por país, cargables desde el admin
Pedido de ella 2026-10-07. Los feriados ya existen y el horario los respeta, pero el maestro
**no se puede tocar sin un deploy**, y lo MEDIDO el mismo día dice que el agujero es grande:

- `country_holidays` tiene **24 filas para 2 países** (Argentina 12, Venezuela 12) de los **22
  países** que el sistema conoce. Los otros 20 no tienen NINGÚN feriado: un negocio de México
  abre el 16 de septiembre porque nadie le dijo que no.
- Las columnas son `month`, `day` y `easter_offset`: **solo entra lo que se repite todos los
  años** (fijo, o calculado desde la Pascua). Un feriado de FECHA concreta —el que mueve un
  decreto, el puente que se anuncia en octubre, el aniversario de una provincia— no tiene
  dónde guardarse.
- No hay maestro en Catálogos ni ruta en el admin: `grep` de `country_holidays` en
  `routes/admin.php` y en `CatalogFormSeeder` no devuelve nada.

- [ ] Maestro de feriados en Catálogos, con el patrón de siempre (tabla + seeder
      `updateOrCreate`), para agregar los que faltan sin tocar código
- [ ] Soportar el feriado de UN AÑO (columna de fecha concreta), que es justo el que hoy no
      entra y el que más se anuncia tarde
- [ ] Cargar los países que ya tienen negocio antes que el resto: hoy son Argentina (9) y
      Venezuela (1), y el resto puede esperar a tener a alguien adentro

---

## B · Esperando la reunión (abogado + contador)

- [ ] **Medio de pago definitivo.** Hoy: comprobante + verificación del admin en
      `/admin/pagos`. Cuando se defina la pasarela entra como otro `method` en `payments`,
      sin tocar pantallas ni historial.
- [ ] **Instrucciones de pago** cargadas en Admin → Compañía (`companies.payment_instructions`)
      antes de salir a producción.
- [ ] **Pregunta abierta de ella:** ¿entra el cambio mensual↔anual? (estándar: pasar a anual
      cuando quieras pagando el año; volver a mensual solo en la renovación).
- [ ] **Moderación de archivos:** qué evidencia se guarda ante un positivo de menores (hoy
      SOLO hash + categoría + puntaje + fecha, el archivo nunca se guarda) · a quién se
      denuncia (NCMEC / autoridades locales) · causales de suspensión y vía de APELACIÓN en
      los Términos · si hay que avisarle a Meta al pasar a la Cloud API.
- [ ] **Términos de uso:** causales por negocio-fachada ilegal. El bloqueo técnico está
      (`SuspendBusiness` + `/admin/moderacion`); el respaldo legal no.
- [ ] **Derecho de supresión de datos** (Ley 25.326 AR y equivalentes): "Eliminar mi cuenta"
      hace borrado lógico. Falta la vía para ANONIMIZAR nombre/correo/teléfono conservando
      estadísticas.

---

## C · Deudas chicas que ensucian el admin

- [x] ~~**El MRR del Inicio estaba inflado 5 veces**~~ (2026-10-07, MEDIDO en `atendia`):
      mostraba 790 y **632 eran los 8 negocios demo de la landing**, que el seeder crea con
      suscripción. El real era 158, y "pagando: 10" eran 2. La regla "los demos nunca inflan"
      existía para el contador público de la landing y a la PLATA nunca se le había aplicado.
      Arreglado en las seis consultas de plata con un solo filtro, y la deducción por correo
      sembrado murió: ahora hay columna `businesses.is_demo`, que es el `test mode` de Stripe
      en un flag. Antes, un demo nuevo con otro correo entraba como cliente real.
- [x] ~~Borrar `demo@atendia.test` de `atendia`~~ (2026-10-04): no lo sembraba ningún seeder
      y no tenía negocio colgando, así que se borró la fila y nada más.
- [x] ~~No hay fila de Compañía en `atendia`~~ (2026-10-07): la cargaste. `Company::count()`
      = 1, con razón social, marca, CUIT, dirección, correo, teléfono, región y condición
      fiscal. Siguen vacíos `web`, los dos logos y `payment_instructions` (esta última
      espera tu reunión): son carga tuya, no código.

---

## D · Candados que al panel admin le faltaban (capa B) — CERRADO 2026-10-03

Los tres guardianes que cerraron los defectos del panel cliente recorrían SOLO
`panel = 'client'`. Se GENERALIZARON a los dos paneles en vez de duplicarse, y se sumó el
cuarto. Lo que quedó, con lo que lo prueba:

- [x] ~~**Pestaña con nombre**~~ → `GoldenRulesScreenTitlesTest`, dataset `panels`. Verde.
      Verificado que no pasa en vacío: las 10 rutas del admin dan 200 y se nombran. La nota
      de abajo era FALSA: `AppLayout` ya resolvía el título por el menú en los dos paneles.
- [x] ~~**Render con la base vacía**~~ → `GoldenRulesFreshScreensTest` (renombrado), dataset
      `panels`. Verde: sin Compañía, sin negocios y sin pagos ninguna pantalla da 5xx ni queda muda.
- [x] ~~**390px y 900px con filas reales**~~ → `PanelResponsiveBrowserTest` (renombrado),
      dataset `panels`. Los 4 recorridos del admin PASAN con 5 negocios, comprobantes,
      tickets, testimonios y moderación. 40 capturas en `tests/Browser/Screenshots/admin-*`.
- [x] ~~**Ítem de menú sin ruta y sin hijos**~~ → dentro de `GoldenRulesLiveControlsTest`
      (es la misma regla de oro, no un archivo nuevo). Atrapó `menu.admin_users` y se sacó
      del `MenuSeeder`: su pantalla es A4 y todavía no existe.

Texto original, para no re-discutirlo. **Sin casilla a propósito** (2026-10-07): escrito con
`- [ ]` se contaba como pendiente abierto en cada revisión, y los cuatro están cerrados arriba.

> **Pestaña con nombre** — hermano de `GoldenRulesScreenTitlesTest` para `panel = 'admin'`
> (`admin.dashboard` y `admin.catalogs` no llaman a `->title()`).
>
> **Render con la base vacía** — hermano de `GoldenRulesFreshClientScreensTest`: sin fila
> de Compañía, sin negocios y sin pagos, ninguna pantalla del admin da 500 (es
> literalmente el hallazgo de la Compañía nula).
>
> **390px y 900px con filas reales** — hermano de `ClientResponsiveBrowserTest` para las
> tablas del admin (pagos, moderación, soporte, adopción).
>
> **Ítem de menú sin ruta y sin hijos no se siembra** (hoy `menu.admin_users` se dibuja y
> no lleva a ninguna parte) — el equivalente de "controles vivos" para el menú.
> Ojo: `check-panel-screen.sh` cierra la dirección **pantalla → ruta → menú**; esta es la
> inversa (**menú → ruta**), que necesita la BD y por eso va de guardián Pest.

Los nombres cambiaron después de escribir esto: hoy son `GoldenRulesFreshScreensTest` y
`PanelResponsiveBrowserTest`, y `menu.admin_users` volvió al seeder CON su ruta
(`MenuSeeder.php:115`), así que no viola el candado.

---

## E · Peticiones de ella del 2026-10-03, cruzadas con lo que ya había

Cada punto dice si es NUEVO, si **ya estaba** (y dónde), o si **ya está hecho**.

### E1. Mover las pantallas del admin a `components/admin/<opción>/` — HECHO 2026-10-03
Regla en `arquitectura-paneles.md`.
- [x] ~~Las cinco movidas~~ con `git mv`: `admin/payments/⚡index`, `moderation/`, `support/`,
      `testimonials/`, `adoption/`. El Inicio ya había nacido en la carpeta nueva.
- [x] ~~`check-panel-screen.sh` ajustado~~ a la forma anidada; las 6 pantallas lo pasan.
- [x] Ojo con la trampa: `admin.payments` era a la vez nombre de RUTA y de COMPONENTE. Solo
      cambió el del componente (`admin.payments.index`); la ruta, el menú y los `route()`
      siguen igual. El primer reemplazo los pisó a los dos y 4 tests se cayeron.
- [ ] **Sin mover a propósito**: `catalog.manager` y los tres de `configuration.*` sirven al
      admin pero viven en sus propias carpetas. `catalog/` arrastra 14 maestros; moverlos es
      otra tarea y hay que decidirla aparte.

### E2. Tickets de soporte en orden de llegada
**AMPLIADO el 2026-10-08 (v2, commit `ac780c3`).** Pantalla de trabajo: cada reporte abre en un panel
lateral con conversación, notas internas (nunca salen al negocio) y ficha del cliente; responsable y
prioridad; cola **Bloqueados** con "qué falta" y "quién sigue"; reloj por tipo y prioridad (ideas sin
reloj). Una respuesta dice cómo salió (WhatsApp, si no correo, si no "sin forma de avisarle") y solo
mueve el reporte si llegó. Las colas son: por responder, con el negocio, bloqueados, resueltos y la ayuda.
- [x] ~~Panel lateral con conversación, notas y cliente~~ (2026-10-08, capturas 1280/900/390 claro y oscuro)
- [x] ~~Entrega real de la respuesta~~ (2026-10-08, 5 casos con test)
- [x] ~~Traspaso "No se pudo resolver" + cola Bloqueados~~ (2026-10-08)
- [x] ~~Vacío con aire bajo los filtros, en el componente para todo el proyecto~~ (2026-10-08, medido)
- [ ] **Sin probar a mano:** el botón del correo de aviso al equipo ahora enlaza a `?reporte=id`. El
      test cubre que el panel abre con ese parámetro, pero no recibí un correo real y hice clic.

**Detalle de la v1 (2026-10-08, base de lo anterior).** La cola pasó a ser la pantalla: tabla con la **espera** de cada reporte
en la primera columna (en dos unidades, "3d 4h"), el atrasado pintado (más de `support.overdue_hours`,
24 h por defecto, editable en Ajustes) y el más viejo sin responder nombrado en el encabezado. Filtros
por cola (por responder, esperando al negocio, resueltos), tipo, negocio y búsqueda sin acentos (en la
URL). La evidencia que captura el widget se lee como datos ("Safari 17 · iOS", "390×844 · teléfono") y
los errores del navegador van aparte; el JSON crudo ya no se muestra. El análisis de la ayuda quedó en
su propia pestaña. Browser test propio a 1280/900/390 en claro y oscuro. "Esperando tu respuesta" se
cambió por "Con el negocio" en el panel admin: leído por ella decía lo contrario.
**Antes:** `/admin/soporte` (`admin/⚡support.blade.php`)
Verificado hoy: ordena por llegada, el más viejo sin responder arriba y lo resuelto se hunde.
- [x] ~~Checklist §7~~ (2026-10-04): sello de frescura en el encabezado, el negocio del ticket
      lleva a su ficha, y el selector de estado dejó de comerse el ancho a 900px (era el
      hallazgo del 03/10: el texto del ticket se partía en 8 líneas).

### E3. Testimonios: ninguno sale sin aprobación
**YA ESTÁ HECHO** · `/admin/testimonios`
Verificado hoy: `Testimonial` solo publica `status = Approved`, y `ModerateTestimonial`
además exige el consentimiento del negocio. La mano del admin es la última puerta.

### E4. Pantalla de clientes que incurrieron en un incumplimiento
NUEVO · **pregunta abierta de ella: qué cuenta como "incumplimiento"**
Hoy los incumplimientos viven desparramados en tres lugares que no se hablan: moderación de
contenido (`moderation_flags` + suspensión), pagos vencidos o pausados, y los reclamos de
soporte. La pantalla que ella pide es el **legajo por negocio**: todo lo que un negocio hizo
mal, en un solo lugar y en orden.
- [ ] **Antes de construir, una pasada de investigación** (pedido de ella, 2026-10-03):
      cómo arman los grandes el expediente de confianza de una cuenta, no una lista de retos.
- [ ] Definir qué entra: ¿solo moderación? ¿también impago? ¿también abuso de la IA?
- [ ] La pantalla (legajo por negocio, con el historial y qué se hizo)

### E5. Datos del WhatsApp del landing configurables en días especiales
NUEVO · hermano de A8 (tags estacionales), misma mecánica de ventana de fechas
- [ ] Número, horario de atención y mensaje de apertura del WhatsApp de la landing, con
      variantes por fecha (feriados, vacaciones, fin de año)
- [x] ~~Se resuelve con el mismo patrón de A8~~: el riel ya está puesto y probado
      (2026-10-06). `seasonal_windows` es compartida y no sabe nada de tags: E5 monta
      encima con una fila evergreen y sus variantes, sin tabla de fechas propia.
      Lo que falta es solo SU contenido — el horario de atención no existe hoy en ningún
      lado, así que esa columna hay que decidirla antes (vive en `companies`).

### E6. Compañía toma vida en todo el proyecto — HECHO 2026-10-03
El nombre NO salía de `config('app.name')`: estaba escrito a mano **140 veces** (118 en
`lang/`, 22 en vistas). Ahora sale de `companies.brand_name` por una sola puerta.
- [x] ~~Columna `brand_name`~~ + campo en la pantalla de Compañía (DTO, Form, vista, lang).
- [x] ~~`Company::brand()`~~: fuente única, con fallback mientras no haya fila.
- [x] ~~Las 118 de `lang/` dicen `:brand`~~ y un traductor propio
      (`BrandAwareTranslator`) lo rellena solo. Sin tocar las 118 llamadas a `__()`.
- [x] ~~Las 22 de vistas~~ leen `Company::brand()`.
- [x] ~~Candado~~ `GoldenRulesBrandSourceTest`: nadie vuelve a escribir el nombre, y PRUEBA
      que renombrar la fila renombra el producto entero.
- [x] ~~Falta que cargues la fila de Compañía~~ (2026-10-07): cargada, `Company::count()` = 1.
      El fallback ya no manda. Quedan vacíos `web` y los dos logos: carga tuya.
- [ ] Correos y errores leyendo dirección y redes de Compañía. **El pie de la landing YA lo
      hace** (`site/footer.blade.php`: redes, dirección, razón social y copyright); los dos
      layouts que faltan leen sólo `Company::brand()` (`email/layout.blade.php:13` y
      `errors/layout.blade.php:12,114`).

### E6-bis. Lo que quedó del pedido original de Compañía — CERRADO 2026-10-05
- [x] ~~Crear la fila de Compañía en `atendia`~~ — la cargó ella el 2026-10-05.
- [x] ~~Marca y redes en `companies`~~ (`brand_name` + `social_links` polimórfica).
- [x] ~~Reemplazar `config('app.name')` en texto visible~~ — quedaba el `<title>` del correo.
- [x] ~~**El wordmark estaba escrito a mano en 5 superficies**~~ (logo de la landing, sidebar
      del panel, wizard, correo y páginas de error), partido con etiquetas:
      `Atend<span>ia</span>`. Ahora es `<x-site.wordmark>`, que lee `Company::brand()` y
      acentúa las dos últimas letras, así el día que la marca cambie cambia en los cinco.
- [x] ~~Pie de la landing con el contacto~~: WhatsApp a un click, correo y dirección con su
      región, todo de la fila y cada línea se esconde sola si está vacía. Es además la señal
      de confianza que pide `atendiadesign` §6: nadie paga si no sabe a quién reclamarle.
- [x] ~~**Una sola puerta para el WhatsApp**~~: `config('atendia.sales_whatsapp')` lo leían la
      landing, los precios y el ticket de soporte, así que cambiar quién atiende era una
      variable de entorno y un deploy. Ahora `Company::whatsapp()`, con el env de respaldo
      mientras la fila esté vacía (igual que `brand()`).
- [x] ~~**Candado**~~ — el guardián existía y no veía nada: buscaba el literal en el CÓDIGO,
      así que un nombre partido por etiquetas y escrito con minúscula se le escapaba. Ahora
      mira el texto IMPRESO (`strip_tags`), sin importar mayúsculas, y solo cuando el nombre
      va suelto — `AskAtendia` o `config('atendia.x')` no son la marca. También saltea
      comentarios de varias líneas, que antes reportaba como si imprimieran.

### E7. Configurar los planes desde el Admin
NUEVO (la pantalla) sobre cimiento YA HECHO
La fuente única ya existe y está blindada: tabla `plans`, clase `Plan`, guardianes
`GoldenRulesPlanSourceTest` y `GoldenRulesPlanPromiseTest`. Falta la cara para editarla.
- [ ] CRUD de planes con el patrón de Catálogos: precio, cupos, consultas de IA, días de
      prueba, "Más elegido", líneas de la ficha
- [ ] Guardar invalida el caché de `Plan` (ya está previsto) y la landing cambia sola
- [ ] Ojo: `GoldenRulesPlanPromiseTest` exige que toda cifra vendida tenga candado, soft con
      razón escrita, o quede anotada como deuda. Editar un plan desde el admin no afloja eso

### E8. Enum que puedan vivir en BD
NUEVO · criterio en `arquitectura-paneles.md`
Hoy hay **25** Enum en `app/Enums/` (eran 23 cuando se escribió; contados el 2026-10-07).
La mayoría son estados que el código ramifica y **se quedan**
(`PaymentStatus`, `MessageDirection`, `SubscriptionStatus`, `ModerationSeverity`…).
- [ ] Revisar uno por uno con ese criterio y pasar a BD solo los que son LISTA
      (candidatos a mirar primero: `SupportTicketKind`, `NotificationType`, `HandoffLevel`)
- [ ] Lo que pase a BD va con el patrón Catálogos: maestro + seeder `updateOrCreate`

### E9. Visibilidad total: clientes, pagos, deudas, próximos pagos
**AMPLÍA A6** (que decía solo "pantalla de negocios")
- [x] ~~La ficha del negocio muestra su historia de pagos, lo que debe, su próximo
      vencimiento y su plan~~ — HECHO 2026-10-03 con A6.
- [x] ~~Vista cruzada: quién debe, quién vence esta semana, quién está en gracia o pausado~~
      (2026-10-07): es la pantalla **Cobranza** (`/admin/cobranza`), y trae lo que faltaba de
      verdad: **cuánto** se juega en cada uno. Ordena por plata en riesgo y no por antigüedad,
      porque la deuda más vieja suele ser la más chica y la llamada que se paga sola es la del
      que está por irse con más. Lo que vence esta semana viaja en la MISMA cola que lo que ya
      se debe: la pregunta es "a quién llamo", y la respuesta es la misma para los tres casos.

### E10. Menú del admin con jerarquía — HECHO 2026-10-03
De 9 ítems sueltos a **6 grupos**, agrupados por lo que ella HACE y rotulados con
sustantivos (criterio investigado: la IA se decide antes que el layout).

```
Inicio
Negocios ──── Todos · Adopción            ← acá entran Incumplimientos (E4) y Radar (A10)
Cobros ────── Pagos                       ← acá entran Planes (E7) y Consumo de IA (A3)
Moderación ── Contenido · Testimonios     ← acá entra Calificaciones de la IA (A9)
Soporte
Plataforma ── Compañía · Catálogos · Integraciones · Logs
                                          ← acá entran Ajustes (A2), Tags (A8), Accesos (A4/A5)
```

- [x] ~~Árbol diseñado y sembrado~~. Cada punto pendiente ya tiene su rama, así que la
      próxima pantalla NO vuelve a reordenar el menú.
- [x] Arreglado de paso: el aviso de Moderación solo recorría el primer nivel, así que
      anidado desaparecía. Ahora es recursivo y el GRUPO también lo lleva — si no, una
      novedad que pide su decisión se escondía detrás de una rama sin abrir.
- [x] ~~Decisión futura: "Cobros" tiene un solo hijo hoy~~ (2026-10-07): ya tiene tres —
      Pagos, Modelos de IA y Consumo de IA. La condición que lo ponía en duda no se cumple.

---

## E11. Livewire 4: islands y lazy en el panel admin
Orden de ella del 2026-10-03: *"el admin necesita tener mucha data en tiempo real; Livewire 4
tiene islands y lazy loading, muy útiles en todo el proyecto."*

Lo investigado en los docs de Livewire 4 (`@island(name:)`, `wire:island=`, `lazy`, `defer`,
`wire:poll`), aplicado al Inicio que ya existe:
- **Island en Acreditar: NO, y hay que decir por qué.** Al acreditar cambian el contador del
  tile Y el MRR. Si la acción solo refresca la isla de la cola, los tiles quedan viejos y la
  pantalla miente (`atendiadesign` §7.1). Hoy re-renderiza todo a propósito.
- [ ] **Island + `wire:poll` en la cola de comprobantes**: ahí SÍ sirve — un comprobante que
      llega mientras ella mira la pantalla aparece solo, sin recargar. Es el "tiempo real"
      que pidió, y el único que no ensucia otro número.
- [ ] **`lazy` / `defer` en lo caro**: decidir MIDIENDO, no por si acaso. Hoy las consultas
      del Inicio son `count()` y sumas chicas; diferirlas sin medir es complejidad gratis.
      Cuando entre Consumo de IA (A3) o el Radar (A10), ahí sí.
- [ ] **Lo que de verdad aguanta MUCHOS negocios no son los islands** (pregunta de ella,
      2026-10-03): es paginar `Business::directory()`, que hoy los trae todos, y precalcular
      los números del Inicio con jobs (§F). El island sirve para el tiempo real; la escala
      se resuelve con paginación y precálculo.
- [ ] Al definirlo, anotarlo como patrón para TODO el panel, no pantalla por pantalla.

### E12. Orquestador de IA — HECHO 2026-10-03 (la mitad que importaba)
- [x] ~~**DEFECTO arreglado**~~: el costo se calcula con la tarifa del modelo que respondió,
      vigente ESE día (`ai_models` con `effective_from`). Un cambio de modelo ya no revalúa
      el pasado. Probado con dos precios del mismo modelo en fechas distintas.
- [x] ~~Maestro `ai_models`~~ con historial de precios + `AiModelSeeder` con la tarifa de
      `gpt-6-astra` verificada y su fuente escrita en la fila.
- [x] ~~Tarea → modelo~~: tabla `ai_tasks` con una fila por agente, todas sin asignar.
      Cambiar el modelo de una tarea es UNA fila; sin fila, manda el `#[Model]` del agente.
- [x] ~~**Tarea → PROVEEDOR**~~ — HECHO 2026-10-04. El `ModelOrchestrator` solo podía
      `withModel()`, así que un modelo de otro lab se mandaba igual a OpenAI. Ahora los 12
      agentes usan el trait `RunsAssignedModel` (`provider()` pisa el atributo) y la fila
      manda proveedor + modelo. Probado con una llamada real que se fue a Anthropic.
- [x] ~~**Pantalla**~~ — HECHA 2026-10-04: `/admin/ia` bajo `Cobros`. Asignación por fila
      (modelo + respaldo) y alta/edición de precios, con su browser test en 3 anchos.
- [x] ~~**Plan B** si el proveedor retira el modelo~~: `ai_tasks.fallback_model_code`. Tiene
      que ser de OTRO lab (la escalera del paquete se indexa por proveedor) y la pantalla
      solo ofrece esos.
- [x] ~~**Falta medir**: las 9 mecánicas~~ (2026-10-08): batería propia (`MechanicalEvalTest`, 25 casos
      objetivos, sin juez) → Astra 25/25 y Luna 25/25, y las 9 corren ahora en `gpt-6-luna` por
      la clave APP, con `gpt-6-astra` de respaldo. La conversación quedó medida con Sol (69/71) y
      NO se asignó: ver el bloque G.
- [x] ~~El aviso cuando entra el respaldo~~ (2026-10-04): `AnnounceModelFailover` escucha
      `AgentFailedOver` y deja un warning con el agente, el proveedor que falló y el motivo.
      Se lee en `/admin/logs`; no hay campana de plataforma (la campana es por negocio), y
      inventarle una era otra pantalla.

### E14. Las dos pantallas de Cobros, rechazadas por diseño
Veredicto de ella, 2026-10-04 al cerrar: *"la ventana de Cobros que hiciste un niño de 2 años
puede hacer algo mejor y con una sola mano"*. Son **Modelos de IA** y **Consumo de IA**: los
datos y los candados están bien (son reales y están probados), lo que no sirve es cómo se ven.
- [x] ~~**Consumo de IA**, rehecha el 2026-10-05.~~ La causa no era cosmética: era una
      pseudo-tabla de `<span>` en flex con los lados en `flex:none`, así que cada fila
      calculaba su ancho por su contenido y NADA se alineaba en vertical entre filas. Ahora
      usa `.pay-table`, la tabla que el proyecto ya tenía (Pagos, Negocios, Moderación,
      Inicio): `<thead>` real, cifras a la derecha en `font-mono`, fila `Total del mes` que
      cierra cada columna, apilado por `data-label` a <991px. De paso: separador de miles en
      español (convivían `4,240,000` y `$161,37`), "hilos" → `Conversaciones`, y un 500 real
      tapado (la × del selector dejaba el mes vacío y `createFromFormat` tiraba excepción).
- [x] ~~**El candado que daba verde con la pantalla rota**~~: `PanelResponsiveBrowserTest` y
      el browser test de la pantalla medían el desborde de la PÁGINA, no el de la tabla
      dentro de su card. `AdminAiUsageBrowserTest` ahora mide el `scrollWidth` de cada
      `.pay-table-wrap`, y atrapó 60px de tabla cortados a 1280px que antes pasaban.
- [x] ~~**Modelos de IA** (`/admin/ia`), rehecha el 2026-10-05.~~ Las dos mitades a `.pay-table`:
      **Tareas** con `TAREA · HOY CORRE · MODELO · RESPALDO` dicho UNA vez (estaba repetido en
      las 12 filas) y **un solo Guardar** en lugar de doce — guarda únicamente las filas que
      cambiaron, y valida todas antes de escribir una (media asignación es peor que ninguna
      cuando el par decide quién le contesta a un cliente). **Precios** en columnas, que es
      para lo que se abre la pantalla: apilados dentro de cada fila no se podía ver de un
      barrido que Claude es más barato que GPT-6 en las tres tarifas.

- [ ] **`Modelos de IA` REHECHA el 2026-10-08, falta su veredicto.** Se investigó (LiteLLM,
      OpenRouter, Portkey, Vercel AI Gateway), se maquetó y ella aprobó la maqueta. Ahora son
      tres pestañas: **Asignación** por tipo de trabajo (conversación, fondo, voz) con un select
      que solo ofrece modelos que sirven para esa tarea y el exacto que corre hoy; **Catálogo**
      con la capacidad de cada modelo (texto, texto y fotos, voz a texto) y su unidad de precio;
      **Conexiones** (una por clave: `openai` = `OPENAI_API_KEY` para audio y búsqueda y
      `openai-app` = `OPENAI_API_KEY_APP` para la IA del cliente, solo lectura). Las tres
      ideas para enamorar entraron el mismo día: asignar un modelo a todo un grupo de tareas,
      el gasto por clave en Consumo de IA y el puntaje de la batería en cada modelo.
      La transcripción dejó de estar clavada.
      Embeddings dejó de estar bloqueado el mismo día: cambiar el modelo es ahora un proceso en
      la tarjeta "Búsqueda del conocimiento" (convertir en paralelo, activar de golpe, volver
      atrás hasta descartar). Lo que sigue abierto de esto: el costo del audio usa UN precio
      por minuto para todo el mes (el del modelo asignado), porque los segundos salen de los
      mensajes y no de un modelo.
- [x] ~~Historia: veredicto de ella, 2026-10-07: *"muy lejos de ser aceptada"*.~~ Verificado el mismo día: el markup ya NO es el problema — la pantalla
      usa `.pay-table` y no queda nada de la pseudo-tabla `aim-*`, así que la reescritura del
      05/10 arregló la estructura y **no ganó su aprobación**. Es un rechazo de DISEÑO, y
      rehacerla a ciegas una tercera vez es la forma más cara de errarle: antes hay que verla
      CON ella (qué espera ver al abrirla y qué decide ahí) o traer referencias reales de
      pantallas de modelos/precios de IA, como se hizo con Cobranza y con el escritorio.
      Ojo: `Consumo de IA` sí quedó aceptada, así que el problema es de ESTA pantalla.

### E14-bis. Las 3 mejoras de Consumo de IA — HECHAS 2026-10-05
Ofrecidas al cerrar el rediseño y pedidas por ella en el acto.
- [x] ~~**Sparkline de 12 meses por negocio**~~ (patrón de Stripe/Vercel): `<x-ui.sparkline>`
      con su test, bajo el nombre en la fila. Los 12 meses salen de UNA consulta
      (`AiUsage::trailingTotals`) y los valora `AiPrices`, la MISMA clase que valora el mes en
      pantalla: la tendencia no puede contradecir a la fila en la que está.
- [x] ~~**Ahorro del caché en plata**~~: bajo cada % de caché, lo que el caché le quitó a la
      factura (`AiPrices::savingOf`). Un porcentaje no es un argumento; los dólares sí.
- [x] ~~**Umbral por plan, con aviso**~~ (patrón del budget alert de OpenAI): columna
      `plans.ai_alert_share`, leída por `Plan::$aiAlertShare`. La fila que lo pasa se PINTA
      (no solo lleva chip), y `atendia:ai-alert` le manda el correo los lunes 09:30 mientras
      siga arriba. Probado que el mismo gasto está arriba del umbral en el plan piso y no en
      Premium. El `atendia.ai_alert_share` global se borró: dos fuentes para lo mismo era
      justo la enfermedad que veníamos sacando.
- [ ] **Queda sin decidir**: el umbral es 35% en los tres planes (lo que había de global). Si
      querés distinto por plan, se cambia en `PlanSeeder` — o entra en el CRUD de planes (E7).

### E13. El Inicio lleva, no solo cuenta — HECHO 2026-10-04
Reclamo de ella: *"la información está bien ordenada pero no aporta casi nada, nada es
clickeable, nada tiene anclada una acción"*. Verificado: de las 4 tarjetas solo Comprobantes
tenía acción en la fila; Renovaciones, Bajas y Vencidos eran 15 filas sin un solo link, y
ningún tile aterrizaba FILTRADO.
- [x] ~~Toda mención de un negocio abre su ficha~~: `#[Url(as: 'negocio')]` en `/admin/negocios`
      hace la ficha direccionable, y las 4 tablas del Inicio enlazan el nombre (`.row-link`).
- [x] ~~El tile de bajas aterriza filtrado~~ (`?estado=canceling`), y Vencidos y pausados cierra
      con su puerta (`?estado=problem`).
- [x] ~~Probado el aterrizaje, no solo el link~~: un browser test hace click en el nombre y
      verifica que la URL lleva el id y que la ficha abrió.
- [x] ~~Progressive disclosure~~ (2026-10-04): las tres tablas de suscripciones son UNA
      tarjeta con pestañas (Renovaciones · Bajas · Vencidos, cada una con su contador), y
      **abre en la que tiene algo trabado**, no en la primera. El Inicio pasó de 4 tablas
      apiladas a 1 cola + 1 tarjeta.

### E12-bis. Lo original, para no re-discutirlo
NUEVO · pregunta de ella del 2026-10-03: *"¿qué pasa si mañana cambio el modelo?"*
Verificado ese día: el modelo está escrito a mano en los **12 agentes** y el costo se calcula
con UNA tarifa global que ignora la columna `model` que `ai_usages` sí guarda.

**Sin casilla a propósito** (2026-10-07): los seis los cerró E12 y escritos con `- [ ]` se
contaban como pendientes abiertos en cada revisión. Lo único vivo de este bloque es la
medición de la línea 540.

> **DEFECTO que se activa al primer cambio de modelo**: el histórico se recalcula con la
> tarifa nueva. Las llamadas viejas quedan valuadas al precio del modelo que no usaron.
> → cerrado: `ai_models` con `effective_from` y unique `code + effective_from`.
>
> **Maestro `ai_models`** con patrón Catálogos: código, precio input/cacheado/salida y
> **vigente desde**. → cerrado, con su pantalla en `/admin/ia`.
>
> **Tarea → modelo**: el agente declara su TAREA, no su modelo. → cerrado: `ai_tasks` +
> trait `RunsAssignedModel`.
>
> **Costo con la tarifa vigente al momento de la llamada**, por `model`. → cerrado:
> `AiModel` resuelve la tarifa del día de la llamada.
>
> **Plan B**: si el modelo configurado falla o lo retiran, cae a uno conocido y avisa.
> → cerrado: `fallback_model_code` en `ai_tasks`.
>
> Para el NEGOCIO el cambio es transparente; para ella no: cambia el costo y puede cambiar
> la calidad. → es el porqué de todo el bloque, no una tarea.

---

## F · Jobs de fondo (no son el panel admin, pero son del mismo plan)

Ideas de ella del 2026-10-03. No son pantallas: son trabajo que corre solo cada X tiempo.
El worker ya existe (supervisor: queue, reverb, schedule), así que el riel está puesto.

- [x] ~~**Alta de cliente nuevo**~~ (2026-10-07): segundo listener colgado de
      `BusinessCreated` — el evento se escribió el día 1 diciendo "mañana van a colgar más
      efectos de acá", y así fue, sin tocar el primero. Le llega a ella por correo con el
      negocio, su correo y su país, y el botón a la ficha.
- [x] ~~**Quién eligió plan de verdad**~~ (2026-10-07): sale en `ApprovePayment`, leyendo el
      estado ANTES de pisarlo con `Active` — después, una renovación y un primer pago son
      idénticos y sólo uno es noticia. **Las renovaciones NO le llegan**: eso es lo que
      convierte un correo en ruido y un buzón en algo que se archiva sin leer.
- [ ] **Jobs que alimenten el dashboard**: los negocios con más movimiento, de WhatsApp, de IA
      — precalculados, no contados al abrir la pantalla
- [x] ~~**Snapshot mensual de MRR**~~ (2026-10-07): tabla `revenue_snapshots` (una fila por
      mes, re-tomarla CORRIGE en vez de duplicar) + `atendia:revenue-snapshot` diario a las
      23:40 — diario y no mensual para que un servidor caído el día 1 no pierda la foto del
      mes para siempre. Guarda el MRR, las suscripciones que lo sostienen y **los movimientos**
      (lo que entró y lo que se fue), porque un total solo no explica nada: ganar 200 y perder
      180 se lee "+20" y esconde que cambió media cartera. La comparación se VE en Cobranza; sin
      mes anterior dice "primer mes medido" en vez de inventar un crecimiento contra cero.
- [ ] **Decisión de diseño pendiente**: un número del dashboard que lo calcula un job lleva
      sello de frescura (`atendiadesign` §7.1). Antes de construirlos hay que fijar cada cuánto
      corren, o la pantalla miente con cara de verdad

> Lo que se le pide a un job acá no es "que corra": es que su resultado se pueda MOSTRAR con
> su frescura dicha, y que si falla se note. Un job que alimenta un tile y muere en silencio
> deja un número viejo con cara de nuevo — exactamente el defecto que §7.1 prohíbe.

---

## G · Lo que quedó abierto de la jornada del 2026-10-08

Modelos de IA, lo contable de Consumo de IA y Soporte quedaron construidos y verificados; esto es
lo único que falta, y casi todo es una decisión o un dato de ella, no construcción.

- [ ] **Decidir la conversación en `gpt-6-sol`.** Dio 69/71 y no se asignó (falló un día futuro y un
      horario incompleto). Para saber si son fallas de Sol o de la prueba falta la misma batería con
      Astra (~3 a 4 USD, en tandas: ver `AssistantEvalTest`). Si Astra falla lo mismo, Sol puede
      tomar la conversación a una quinta parte del costo.
- [ ] **Cargar los costos fijos del mes** en Ajustes → Costos fijos (dato de ella). Hasta entonces
      el resultado neto de Consumo de IA no se muestra.
- [ ] **Revisar en platform.openai.com** que la clave APP esté en un Project distinto del de la
      clave principal, con su presupuesto y su alerta (dato de ella).
- [ ] **Modelo de voz.** El que corre (`gpt-4o-transcribe-diarize`) no tiene precio verificado: su
      costo se estima con 0,003 por minuto. Asignar uno del catálogo (`gpt-4o-mini-transcribe`,
      0,003) vuelve real la cifra, pero la calidad del audio no tiene batería.
- [x] ~~**Migración de embeddings**~~ (2026-10-08): en Modelos de IA → Búsqueda del conocimiento. Convierte
      las 7 tablas con vectores en una columna paralela (`embedding_next`), activa de golpe renombrando
      el par (ninguna consulta cambia), conserva el anterior (`embedding_prev`) para volver atrás hasta
      que se descarta, y cancela antes de activar. Los modelos salen del catálogo (capacidad nueva
      "Búsqueda por significado", con precio y dimensiones; seed: 3-small 0,02 y 3-large 0,13 USD por
      millón, verificados). Probado con embeddings simulados (ciclo completo, índices, vuelta atrás) y
      en navegador a 1280/900/390 claro y oscuro.
- [ ] **Probar un cambio real de embeddings** contra OpenAI cuando ella quiera: no se hizo porque gasta
      tokens (hoy son 36 fragmentos y 15 intenciones: centavos). Con 3-large hay que elegir ≤2000
      dimensiones (pgvector no indexa más); el catálogo lo trae a 1536.
- [ ] **Veredicto de ella sobre Modelos de IA** (E14), rehecha hoy con su maqueta aprobada.
- [ ] **Veredicto de ella sobre Soporte** (E2), reconstruida hoy y ampliada (v2): conversación con
      resultado de entrega real (WhatsApp, si no correo, si no "sin forma de avisarle"), notas
      internas, responsable y prioridad, ficha del cliente, traspaso "No se pudo resolver" con cola
      Bloqueados, reloj por tipo y prioridad (las ideas no corren reloj). La clave `estado` de la URL
      pasó a `cola`; el reporte abierto va en `reporte`.
- [x] ~~**Las 3 ideas de Soporte**~~ (2026-10-08): atajo "Mis reportes" en la barra de colas; respuestas
      guardadas como maestro en Catálogos (grupo Soporte, 5 de arranque que ella puede reescribir) y
      selector en el composer; mediana de resolución por tipo en el encabezado (90 días, "—" con motivo
      si no hay resueltos).

---

## H · Reclamos de diseño y de producto de ella, 2026-10-08 (anotados, sin construir)

Los tres salieron de usar el admin de verdad. Nada de esto se tocó: cada uno pide su pasada de diseño
(maqueta primero, capturas a 1280/900/390 en claro y oscuro) antes de construirse.

- [ ] **Auditoría está "horrible": la columna "Qué pasó" muestra tanto texto que hay que bajar mucho para llegar a la fila siguiente.** Es la tabla `audit` del admin (`components/admin/audit`). Hay que rediseñarla como pantalla de operación (`atendiadesign` §7.3): fila compacta con el resumen en UNA línea, el detalle (qué cambió, valores antes y después) plegado o en un panel lateral al abrir la fila, y la acción y el autor legibles de un barrido. Medir con filas reales largas, no con las de prueba.
- [ ] **Usuarios y accesos → "Crear usuario": el campo Nombre está muy ancho y deja al último campo (Rol) demasiado angosto, así que se corta su contenido.** Es el formulario `AdminUserForm`. Repartir la fila por contenido (`span=`: el nombre baja de ancho, el rol gana) según `formularios.md`, y verificar a 390 y 900 px con el rol más largo.
- [ ] **Doble factor: se ofrece, pero el admin no tiene dónde crearlo ni gestionarlo.** Hoy existe SOLO en Ajustes de cada cuenta (cada persona lo activa por WhatsApp, con códigos de recuperación) y la pantalla de Usuarios apenas lo MUESTRA como una columna de solo lectura ("Activo" o "—"). **DECIDIDO por ella el 2026-10-08: las tres cosas.** (1) **Ver quién lo tiene**: la columna actual más un filtro y un conteo. (2) **Exigirlo** solo a admin y soporte (a los clientes se les ofrece), con plazo de gracia (ej. 7 días) y no con bloqueo inmediato; ojo que el código llega por NUESTRO WhatsApp no oficial, así que si el canal cae mientras es obligatorio nadie del equipo entra: requiere códigos de recuperación a mano y una salida de emergencia para la dueña. (3) **Restablecerlo**: solo la dueña (soporte no), pidiéndole su contraseña, dejando constancia en Auditoría, avisando por correo a la persona afectada y obligándola a reactivarlo en su próximo ingreso; es la vía para tomar una cuenta ajena, por eso no es un botón suelto. Cada una va con su test de acceso (cliente y soporte ↛ restablecer = 403).
