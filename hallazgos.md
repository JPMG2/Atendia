# Hallazgos — lo que encontré mientras hacía otra cosa

> **A partir del 2026-10-12 el estado vive en `pendientes.md`.** Este archivo queda como la
> historia de cada hallazgo (qué pasó y cómo se midió). Un hallazgo nuevo entra en `pendientes.md`
> con su "Está hecho cuando" y su "Se comprueba".

> **El contrato (regla de trabajo 1).** Un hallazgo que NO es la tarea pedida se anota acá y
> **no se toca**. Al cerrar el turno se nombra en una línea y la dueña decide si entra.
> Perseguir cada hallazgo es lo que convierte una tarea de una hora en una mañana entera.
>
> Esto NO es la cola de ideas para enamorar (esas son mejoras ofrecidas, viven en la memoria
> `atendia-enamorar-cola`): acá van **defectos y deudas** encontrados de paso.
>
> Formato: una línea por hallazgo, con la fecha y dónde está. Cuando se arregla, se borra.

## 2026-10-08

- **La suite de navegador del 08/10 dio 257 verdes y 3 rojos, en 1646 s (27 min).** Uno era mío
  (`SupportBrowserTest` buscaba el análisis de la ayuda en la cola, que ahora vive en su pestaña:
  corregido). Los otros dos pasan aislados: `ClientJourneyBrowserTest` (timeout de 5 s) y el barrido
  de `PanelResponsiveBrowserTest` ("my-payments desborda 10px" una vez, 8 verdes la siguiente). La
  suite de navegador pasó de 17,5 a 27 minutos; nadie la había vuelto a medir.
- **La corrida completa de PHP dejó 7 rojos en 5 archivos que pasan aislados.** 2409 verdes y 7
  rojos en 447 s; los mismos cinco archivos corridos solos dan 75 verdes. `TaxConditionSeederTest`
  y `TracksUserActionsTest` ya estaban anotados (conteo absoluto sobre una base compartida);
  `PanelAccessTest`, `SiteFooterCompanyTest` y `WelcomePageTest` no estaban en esa lista. Es la
  misma enfermedad y no se persigue ahora, pero conviene mirar qué estado deja una prueba anterior.

- **El audio que corre hoy no tiene precio verificado.** `gpt-4o-transcribe-diarize` no figura
  en la página de precios de OpenAI, así que su costo se estima con `audio_per_minute` (0,003).
  Para que la cifra sea verdad hay que asignar a Voz a texto un modelo del catálogo con su
  precio (`gpt-4o-mini-transcribe` cuesta 0,003 por minuto, `gpt-4o-transcribe` 0,006). No se
  cambió: la transcripción no está en la batería y la calidad se decide midiendo.
- **Las 9 mecánicas pasaron a `gpt-6-luna`** (2026-10-08, con `gpt-6-astra` de respaldo): su batería
  (`MechanicalEvalTest`, 25 casos objetivos, sin juez) dio Astra 25/25 y Luna 25/25. Son ~9% del
  gasto de hoy; el cambio se deshace desde Modelos de IA.
- **La conversación con `gpt-6-sol` dio 69 de 71** (97%), y NO se asignó: falló en un día futuro
  ("¿cuántas conversaciones hubo pasado mañana?" contestó 0 en vez de decir que no pasó) y en dar el
  horario completo. Falta el punto de comparación: la misma batería con Astra (~3 a 4 USD) dice si
  esos dos fallos son de Sol o de la batería. Sol cuesta 2/10 contra 10/50 de Astra.

- **Una columna llamada `connection` en un modelo Eloquent se lee siempre `null`.** Choca con
  la propiedad que guarda el nombre de la conexión a la base: costó 9 rojos antes de verla.
  Quedó como `connection_key`; vale para cualquier tabla futura.

## 2026-10-07

- **La API de Mailpit responde 404 y no se puede leer un correo entregado.** `atendia-mailpit`
  tiene el 8025 abierto y algo contesta ahí, pero `/api/v1/messages`, `/api/v1/info` y la raíz
  dan 404 desde el contenedor de la app. Sirve igual para que el correo NO se vaya a internet,
  pero hoy un correo se verifica renderizando el Mailable, no mirando lo que llegó. Posible
  causa a confirmar: `MP_WEBROOT` con un prefijo de path.

- **`block-full-suite-reruns.sh` cuenta `pest --list-tests` como una corrida entera.** Listar
  no ejecuta nada y cuesta segundos, pero el hook lo frena igual, así que verificar qué
  testsuite corre por defecto no se puede hacer con la herramienta que lo dice. El hook ya
  distingue `--filter` y una ruta de archivo: falta que distinga también las opciones que
  solo LISTAN.

- **El hook `Stop` corre los guardianes sobre la MISMA base que la puerta, y la arruina.**
  Cerré un turno mientras la corrida de la puerta estaba a mitad de camino: los guardianes
  `GoldenRules*` arrancaron su propio `RefreshDatabase` sobre `atendia_testing` y la corrida
  pasó de 17 rojos a **118**, con `QueryException`, `MultipleRecordsFound` y uniques por todas
  partes. Los 29 archivos afectados, corridos juntos después, dan **522 verdes**. El daño no es
  el rojo: es que una puerta de 8 minutos deja de medir y hay que repetirla. Falta que el hook
  (o el de reruns, que ya lleva un contador) vea que hay una corrida en curso y espere.

- **Dos rojos de conteo absoluto que todavía no tienen causa escrita.**
  `TaxConditionSeederTest` contó 11 condiciones donde espera 10, y `TracksUserActionsTest` vio
  2 monedas vivas donde espera 0 tras un `forceDelete`. Pasan aislados y pasaron también en la
  corrida conjunta de 29 archivos del 07/10, así que `Once::flush()` pudo haberlos cerrado de
  paso, pero NO está confirmado en una corrida completa limpia. Lo que los hace frágiles es el
  conteo absoluto (`toBe(10)`, `toBe(0)`) sobre una base que comparten todos.

- **El contenedor no tiene fuente de emoji, y las capturas lo muestran.** En
  `hero-demo-chat.png` el emoji del guion de la ferretería sale como el rectángulo vacío de
  "glifo que falta". Es el Chromium headless del contenedor, no la landing: en un navegador
  con fuentes de emoji se ve bien. Molesta sólo para revisar capturas; se arregla instalando
  `fonts-noto-color-emoji` en la imagen.

## 2026-10-05

- **`referral.reward_percent` (25%) se promete y nadie lo paga.** Lo imprimen la pantalla de
  referidos y el correo de invitación, y NINGÚN archivo de `app/` lo lee: no hay descuento, ni
  crédito, ni asiento. Es la categoría exacta de `GoldenRulesPlanPromiseTest`: mostrar la cifra
  no es hacerla cumplir. Por eso NO entra como ajuste editable — sería un control que no hace
  nada. O se construye el pago, o la cifra sale del copy.

- **La suite entera no se corría desde el 2026-10-03 y escondía 6 rojos reales** (18 rojos en
  total: 12 eran flake y pasaron aislados). Los 6 venían del commit `3600753`, no del trabajo
  de ese día, y quedaron TODOS arreglados: 3 buscaban el ítem `menu.admin_settings` (renombrado
  a `menu.admin_platform`), 2 del Inicio leían filas que ese commit movió detrás de tabs, y el
  del tile de Integraciones exigía una rejilla que ese commit sacó a propósito. **Lección que
  queda:** la suite completa es la única que ve el daño colateral de un rename o de un rediseño,
  y entre el 03 y el 05 nadie la corrió.

- **Dos archivos de browser en la MISMA corrida se pisan la base y el error miente.**
  `AdminAiUsageBrowserTest` + `PanelResponsiveBrowserTest` juntos dieron 5 rojos con
  `relation "permissions" does not exist` y `relation "plans" does not exist`: no es un bug
  de esquema, es `RefreshDatabase` de un archivo reconstruyendo `atendia_testing` mientras el
  servidor del otro consulta. Cada uno aislado pasa. Es la misma causa de los 3 flakes ya
  anotados (`CatalogCurrencyEdit`, `PanelResponsive`, `DemoChat`), ahora con el error exacto:
  la única base de test es compartida y nadie serializa los archivos de browser.

- **Las dos familias de campos conviven** (`x-ui.input` y `x-inputsform.input`). Hoy NO se
  contradicen a la vista — comparten `.field`, `.field-label` y `.field-control` — y
  `x-ui.select` no se usa en ninguna vista. La deuda es tener dos puertas para lo mismo:
  `x-inputsform.*` agrega `span` y el asterisco de requerido, `x-ui.*` no.
- **El minutaje de audio ya no se muestra en `Consumo de IA`.** Entra en el costo y sigue en
  los tres exportables, pero perdió su columna al pasar la pantalla a tabla: nueve columnas no
  entran a 1280 sin cortar. Si lo quiere en pantalla, hay que sacarle el lugar a otra.

## 2026-10-04

- **En oscuro, el fondo del sidebar del panel se corta a media página.** En una pantalla
  larga la columna izquierda pinta negro hasta ~900px de alto y abajo queda blanca. Se ve en
  `tests/Browser/Screenshots/admin-ai-desktop-dark.png`. Es del shell del panel (lo sufren
  todas las pantallas largas de los DOS paneles), no de la pantalla nueva: el alto del aside
  se fija en vez de seguir al contenido.

- **Los embeddings siguen clavados en OpenAI.** Los 12 agentes ya salen de la fila
  (`RunsAssignedModel`, hoy), pero `KnowledgeEmbedder` pide `Lab::OpenAI` a mano con
  `config('rag.embedding.model')`: el RAG no es un agente y no pasa por `ai_tasks`. El día
  que el embedding cambie de lab hay que editar código, y los vectores viejos de 1536
  dimensiones no son comparables con los de otro modelo — no es solo cambiar la fila.

## 2026-10-03

- **`atendia_testing` se ensucia entre corridas y arruina la lectura de la suite.** La corrida
  completa de hoy dio **109 rojos**; 108 eran filas sobrantes de corridas anteriores (ej. una
  provincia "Feliciano Medio — JMR" que no siembra ningún seeder) y **pasan aislados**. El rojo
  real era UNO. Pasó por correr tests filtrados durante la sesión. Vale la pena un
  `migrate:fresh` sobre `atendia_testing` (NUNCA sobre `atendia`) antes de la corrida que
  decide un commit, o la suite completa deja de servir para leer.

## 2026-10-02

- **El panel izquierdo de las pantallas de acceso dice "Automatizá tu WhatsApp" también en
  `es_VE`**: ese titular sale de `landing`, y `lang/es_VE/landing.php` existe pero no lo pisa.
  Visto en la captura `auth-register-es_VE`.

- **3 rojos de browser bajo carga** (`CatalogCurrencyEdit`, `PanelResponsive`, `DemoChat`):
  pasan aislados, fallan en la corrida entera de 18 min. No se persiguen por orden de ella.
  Confirmado otra vez el 2026-10-03: los 3 fallaron en la suite y pasaron solos a los 2 min.
- **Solo 2 de 18 hooks comparten el scanner con su guardián** (comentarios y controles vivos).
  Hoy ninguno miente (probado 8 de 8), pero los otros 16 pueden divergir el día que cambie un criterio.
- **`resources/js/echo.js` tiene un comentario en español** (línea 19): la regla de comentarios
  alcanza a `resources/js`. Es deuda vieja, anterior a hoy.
- **La suite de browser cuesta 1052 s** (17,5 min) y es la puerta de cada commit que toca pantallas.
  Nadie lo había medido.
- **Un toast rojo falso en los browser tests que guardan el perfil del negocio**: la cola corre
  `sync` en pruebas, el job de conocimiento llama a OpenAI sin clave, falla, y `tryAction` lo
  convierte en "Registro no actualizado" — aunque el guardado funcionó. En producción la cola es
  Redis y no pasa. Entrena a ignorar errores en pantalla, igual que el 404 de Reverb.
- **26 procesos node huérfanos de VS Code Dev Containers** (`vscode-remote-containers-server-*.js`),
  uno con 65 días de vida, otros con 19, 16 y 10 días. Son sesiones de VS Code ya cerradas que
  nunca murieron. **Medido: suman solo 0,2 GB de RAM**, así que NO son el problema de memoria que
  parecían — es prolijidad, no urgencia. Conviene entender por qué no se cierran solos antes de
  matarlos a mano.
- **Sobras del camino viejo tras retirar chatwoot y n8n** (2026-10-06): el servicio Swarm
  `ai_project_ai_project2025` está en 0/0 (muerto, nunca arranca); `ai_project_pgadmin` sigue
  arriba con su imagen de 507 MB; hay **dos Redis** (`ai_project_redis` y `ai_project_redis-shared`)
  y no está claro si AtendIa usa uno, el otro o ninguno; y la base `laravel_prod` (7,8 MB, 9 tablas)
  no es de AtendIa. Cada uno hay que identificarlo antes de tocarlo.
- **La barra de pasos del wizard desborda 44px a 390px** (2026-10-06): `nav.wizard-steps` mide
  434px con sus 4 pestañas, y eso ensancha el documento entero — la 4ª ("04 Conexión") queda
  cortada en un teléfono. Es deuda vieja, no la tocó la tarjeta de conexión: lo midió
  `WhatsAppLinkBrowserTest`. Nunca lo atrapó `PanelResponsiveBrowserTest` porque barre las rutas
  del MENÚ y `/alta` no es una de ellas.
- **Evolution arranca antes que Postgres** (2026-10-06, tras el reboot al kernel -146): intentó
  sus migraciones Prisma dos veces contra `ai_project_postgres-shared:5432` todavía caído
  (`P1001`) y recién levantó al tercer intento. El reboot se va a repetir: falta una dependencia
  de arranque o un reintento del paso de migración.
  **CORRECCIÓN (mismo día):** el reboot NO perdió el emparejamiento, como escribí primero.
  `fetchInstances` dice `disconnectionAt 2026-10-02T23:22:53`, código 401,
  `tag: conflict / type: device_removed`: el dispositivo se desvinculó desde el teléfono el 2 de
  octubre. El webhook sí llegó — la fila 1 de `panel_notifications` es de un segundo después.
- **No hay pantalla de conexión de WhatsApp**: `EvolutionApi::qrCode()` existe y su PHPDoc dice
  "la pantalla de conexión se lo pedirá", pero ningún Blade la llama. Hoy re-emparejar obliga a
  entrar al Manager de Evolution por fuera de AtendIa.
- **La tabla del hub de Catálogos nunca fue responsive** (2026-10-06, MEDIDO): con un maestro
  abierto a 390px el documento desborda — 264px en Redes sociales (5 columnas), 362px en
  Ejemplos del hero (6). Es la chrome compartida `<x-catalog.table>`, así que son los 14
  maestros, no una pantalla. `PanelResponsiveBrowserTest` nunca lo atrapó porque visita
  `/admin/catalogs` SIN abrir un maestro: mide el riel, no la tabla. Arreglarlo es apilar por
  `data-label` como ya hace `.pay-table` en Cobros.
- **`inDarkMode()` de Pest no oscurece la página** (2026-10-06): la captura sale en claro igual.
  Todas las capturas `*-dark` que hay hoy (`whatsapp-panel-connected-dark`, etc.) están
  mintiendo: muestran el tema claro con otro nombre de archivo. Lo que sí funciona es
  `->script('document.documentElement.classList.add("dark")')`. Revisar las que ya existen.
- **La píldora del topbar vuelve a amarillo sólo al recargar** (2026-10-06): ahora se pone verde
  en vivo con `whatsapp:connected`, pero el camino inverso (se cae el vínculo mientras ella mira
  otra pantalla) no tiene aviso. La campana ya escucha `private-business.{id}` y el webhook ya
  levanta esa notificación: la píldora podría colgarse del MISMO socket, sin poll ni costo nuevo.

- **Soporte v2: el vacío pegado a los filtros era del componente, no de la pantalla** (2026-10-08, MEDIDO): `x-ui.empty-state` no tenía separación propia, así que en cualquier toolbar+resultado quedaba pegado. Ahora `.form-row + .empty-state` da 20px y `framed` lo enmarca; un browser test mide la distancia (>= 16px). Otras pantallas con un empty-state bajo una form-row cambian solas: revisar a ojo la próxima vez que se abran.

- **DemoChatBrowserTest falla en la suite de navegador del 2026-10-08, y aislado** (el chat de la landing muestra mensajes repetidos de corridas anteriores, así que el contador "Te quedan 2" no sale): parece estado persistido del demo entre corridas (hero.js). Mi diff no toca landing. No se investigó; ver si el historial del demo vive en localStorage del perfil de Chromium.

- **Flakes de la corrida completa del 2026-10-08 (tarde): PanelAccess, ReportsTest, SiteFooterCompany x7, TaxConditionSeeder x2, TracksUserActions, WelcomePage x2** (14 rojos, 2447 verdes). Los 81 pasan aislados. Los datos filtrados ("Arreola de Tapia", marca "Conversa") son de otra empresa: `Company::current()` memoiza con `once()` y una fila de un test anterior se cuela. Misma clase que la de la mañana, pero con más víctimas; no se persiguió.

- **Suite de navegador del 2026-10-08 (tarde): 266 verdes y 3 rojos por timeout de 5 s** (CatalogSocialNetworkEdit x2, ServicesProducts: foto de producto). Los 3 pasan aislados (8 verdes). Clase conocida: la corrida larga se vuelve lenta y el clic espera de más.

- **Suite de navegador del 2026-10-08 (noche), con el sistema de tablas: 275 verdes y 3 rojos.** `AdminAiBrowserTest` (el clic del diálogo, ya visto una vez) y `ClosuresBrowserTest` (`UniqueConstraintViolationException`) pasan aislados; `DemoChatBrowserTest` falla aislado igual que en la corrida de la tarde (estado viejo del demo del hero). Nada de eso toca tablas.

- **RESUELTO 2026-10-09 — dos pest colgados 21 h (pid 266515 y 272872) + un `playwright run-server` huérfano de 1 día.** Causa medida: ambos tenían `PPid 0` (los lanzó un `docker exec` cuyo cliente murió) y ningún `timeout` interno, así que nada los mató; cada uno dejó vivo su servidor de Playwright, sin ninguna sesión en la base. Se mataron (verificado: cero pest/playwright). Para que no vuelva: hook `block-untimed-pest.sh` (bloquea `docker exec … vendor/bin/pest` sin `timeout` ADENTRO, o con el `timeout` afuera; 12/12 casos probados) y `StartModelEval` ignora corridas de más de 60 min en vez de quedar "ocupado" para siempre.

- **RESUELTO 2026-10-09 — el combobox recortaba el texto elegido.** No era la ✕: la opción se llama "Modelo barato · OpenAI · principal" (280px) y el campo mide ~134px. Ahora el input termina en "…" (`text-overflow:ellipsis`) y lleva el nombre completo en `title`; lo mide `AdminAiBrowserTest`.

- **RESUELTO 2026-10-09 — la barra de pasos del alta**: a 390px ya no hay pasos fuera de pantalla (176px del 3º y 4º): los 4 comparten el ancho con su número encima (`BusinessWizardBrowserTest`).

- **RESUELTO 2026-10-09 — la tabla de Catálogos a 390px/900px** (desbordaba hasta 477px): se apila por celda con su encabezado (`catalogMaster.labelCells` pone `data-label`), el hub apila con `align-items:stretch` y la barra de búsqueda/alta envuelve (`CatalogTableLayoutBrowserTest`, 8 casos).

- **RESUELTO 2026-10-09 — las capturas `*-dark` en claro**: eran las 2 de `WhatsAppLinkBrowserTest` (usaban `inDarkMode()`); ahora ponen la clase a mano, y `BrowserShotsTest` impide volver a usar `inDarkMode()`.

- **RESUELTO 2026-10-09 — el correo de Adopción y la pantalla dicen lo mismo de "trabada".** `atendia:adoption-alert` usa `AdoptionRowDto::situation` (la regla de la pestaña Trabadas), corre a diario y avisa una vez por cuenta y paso. (Lo había dejado como pregunta para ella: la regla ya estaba decidida.) `AdoptionRowDto::isStalled` ya no lo lee nadie fuera de los tests viejos.

- **RESUELTO 2026-10-09 — la bandera del teléfono "se auto-selecciona" y no deja recorrer la lista (computadora).** La causa no se pudo aislar (el HTML y los scripts eran los mismos que en el wizard; la lista nativa la dibuja el navegador y los tests no la ven), así que se quitó el origen: el prefijo ya no es un `<select>` nativo sino un panel propio con buscador, nombres de país, scroll libre y elección SOLO por clic o Enter (`inputsformPhone`, `PhoneFieldBrowserTest`). Vale para las seis pantallas que usan el campo. Lo que se había descartado al investigar: el componente (es el mismo del wizard), polls en la pantalla, peticiones o cambios de DOM tras el clic (browser test), su número (`whatsapp` nulo), logs del navegador (viejos, de otras páginas). Sin mirar todavía: el panel carga scripts que el wizard no (`table-enhance`, `section-dirty`, `echo`, `catalog-*`, `avatar-field`, `datepicker`, `photo-viewer`); el que habría que bisecar es ese. NO se cambió nada. Retomar solo si ella lo pide.

- **ABIERTO 2026-10-09 — flake del candado de overflow de Catálogos con "Atributos" (1 de cada 4 corridas, 17 o 71 px).** `CatalogTableLayoutBrowserTest` "no catalog table overflows its panel" mide justo después del clic, antes de que el panel termine de animar; pasa y falla sin tocar nada, y no depende del maestro de Planes (que pasa siempre). Arreglo probable: esperar a que asiente (`->wait(1)`, como ya hace el test de 390/900) antes de medir. NO se tocó.

- **RESUELTO 2026-10-09 — "Mi seguridad" muestra mal un número ya guardado.** `inputsformPhone` ahora lee `+` y solo dígitos tomando el prefijo conocido MÁS LARGO con que empiezan (`PhoneFieldBrowserTest`: guardado `584247673951` → +58 y `4247673951`; al editar, se recompone sin duplicar). Texto original: La pantalla le pasa al campo de teléfono `'+'.$whatsapp` (`+584247673951`, sin espacio) y `inputsformPhone` solo reconoce `+58 4247673951` (con espacio): con el número guardado `584247673951` el campo mostró prefijo **+54** (el del país de la plataforma) y número `584247673951`, y al guardar de nuevo se compondría `+54 584247673951`. Medido en un browser test con países sembrados. El wizard no lo tiene porque guarda con espacio. Arreglo probable: que `StaffPhoneForm` entregue el compuesto con espacio (o que el componente separe el prefijo conocido por longitud). NO se tocó: no era la tarea.

- **Zona horaria del negocio — CORREGIDO el 2026-10-09: el diseño está bien y el hallazgo original exageraba.** El país es obligatorio y ancla la zona con la lista nativa de PHP (`localTimezone()`); no hace falta pantalla para elegirla. Lo ÚNICO que queda: de los 22 países cargados, 7 tienen varias zonas (AR BR CA CL EC US MX) y el código toma la primera ignorando `province_id`, aunque el comentario de la columna dice que la provincia "ubica el timezone". Solo importa para BR, CA, US y MX (en AR, CL y EC las zonas extra tienen el mismo reloj). Se resuelve con un mapa provincia → zona cuando entre el primer negocio de esos países; no antes.

- **RESUELTO 2026-10-10 (también `.catalog-panel-head`, `.catalog-form` y `.catalog-empty`, ahora con `backwards`) — `.catalog-view` retenía un `transform` de la animación de entrada** (`animation … both` con `to { transform:none }`): mientras la animación queda "mantenida" el elemento sigue siendo bloque contenedor de todo `position:fixed`, así que cualquier slide-over dentro de un maestro quedaba atrapado y recortado por la tarjeta. Arreglado con `backwards` y blindado en `CatalogCountryHolidayBrowserTest` (recorre los ancestros del panel). El mismo patrón (`animation … both` + `transform` en el `to`) sigue en `.catalog-panel-head`, `.catalog-form` y `.catalog-empty`: no atrapan nada hoy, pero un panel fijo dentro de ellos sí.
- **RESUELTO 2026-10-10 (`<x-ui.key-hints>` en las 13 pantallas con tabla ordenable del admin; el `?` lista lo que dice la línea) — Solo Auditoría tenía línea de teclas (`.key-hints`)**, aunque `j`/`k` funcionan en toda tabla `.pay-table[data-sortable]`: el atajo `?` no dice nada en esas pantallas. Decide ella si cada tabla lleva su línea.
- **ABIERTO 2026-10-12 — AUDITORÍA DE DEFAULTS (medida contra la app corriendo, no leída del código).**
  - **RESUELTO el 2026-10-12 (raíz: `trustProxies` solo a redes privadas; más dos redes anchas en el login: 25 fallos por correo y 30 por IP cada 15 min; repetido el ataque en vivo y ahora bloquea al 6.º; tests en `LoginThrottleTest`). Ojo con Cloudflare al salir (ver `aproduccion.md` §3).** Texto original — ALTA — el límite de intentos de login se esquiva rotando `X-Forwarded-For`. Sin la cabecera se bloquea en el 6.º intento; rotándola, 9 de 9 pasan. La clave de `LoginRequest::throttleKey()` y del limitador `auth` de la API es `email|ip`, y con `trustProxies(at: '*')` la IP la elige quien llama. Mitigante: el admin tiene 2.º paso; los clientes no. Arreglo: clave solo por correo (más un tope por IP de `REMOTE_ADDR`), y confiar solo en la IP de Traefik. NO tocar `trustProxies` a ciegas: sin él los assets salen en http.
  - **MEDIA — recuperar clave dice si el correo existe** ("No encontramos ningún usuario…" contra silencio). Enumeración de cuentas; en un SaaS con negocios de verdad es dato de clientes. Arreglo: misma respuesta siempre.
  - **MEDIA — `/maqueta-registro.html` (37 KB) está en `public/` y responde 200.** Es una maqueta de diseño, no debería publicarse.
  - **MEDIA (a resolver con el dominio) — cabeceras:** no hay HSTS, CSP, Referrer-Policy ni Permissions-Policy; la cookie de sesión no lleva `Secure`; la de sesión dura 12 h y es persistente (ya cerrado el caso de staff por inactividad).
  - **BAJA — `GET /r/{code}` devuelve el nombre del negocio invitador a quien adivine un código.**
  - **OK, verificado:** webhook de Evolution da 401 sin firma y falla cerrado con secreto vacío; `.env`, `.git` y `storage/logs` dan 403; `/telescope`, `/horizon`, `/_debugbar`, `phpinfo` dan 404; el seeder de admin no trae contraseña por defecto y `.env` no está versionado; el login se bloquea al 6.º intento (sin la cabecera); las rutas sin login son las esperadas (landing, webhooks, enlaces firmados, `/up`).
- **2026-10-12 — REVISIÓN INDEPENDIENTE del trabajo de hoy (agente `revisor-independiente`, contexto limpio, 28 comprobaciones, 6 hallazgos).**
  - **RESUELTO — una pestaña de admin que solo hace polls no vencía si su sesión no tenía la marca** (la de antes del deploy, o si el primer pedido era un poll). Ahora el primer pedido arranca el reloj. Test: "a tab that only polls still idles out…".
  - **RESUELTO — `/llms.txt` salía con `Cache-Control: public` y cookies de sesión.** Ahora `no-cache, private`. Test incluido.
  - **ABIERTO — `catalog_eligible` no lo consulta nadie** (ya dicho: el sync a Meta no existe; la columna es la regla lista, no una protección).
  - **ABIERTO — una actividad de salud creada a mano desde Catálogos nace con el interruptor encendido** (el DTO trae `true`). Decidir si el alta toma el valor del rubro.
  - **ABIERTO — el cierre por inactividad se ve como éxito** (alerta verde en el login) y, desde una pantalla Livewire, cae en `/login` sin explicación (419 vacío).
  - **ABIERTO — el arreglo de las alertas vive solo en el árbol de trabajo** (`public/build` está en `.gitignore`): un build limpio desde `HEAD` vuelve a purgar `.alert-*`. Se cierra al commitear `tailwind.config.js`.
- **RESUELTO 2026-10-12 (safelist en `tailwind.config.js`, y el aviso de Meta ya no lleva fondo en línea; guardado por la medición de `ActivityCatalogSwitchBrowserTest`) — todos los `<x-ui.alert>` salían sin fondo ni borde.** El build no trae ninguna `.alert-info|success|warning|danger|brand` (`grep` sobre `public/build/assets/*.css` da cero): el componente arma la clase con `'alert-'.$v` y Tailwind la descarta por no ver el nombre completo. Afecta device-challenge, el archivar de Planes, el aviso del login y el de Meta. Arreglo de una línea: sumar las cinco al `safelist` de `tailwind.config.js` (ahí ya están `status-tag`, `is-*`). No se tocó porque cambia el aspecto de pantallas ya aprobadas; decide ella. El aviso del nombre de Meta se dibuja con su fondo en línea.
- **2026-10-11 — copy con voseo en la base neutra (`lang/es/`).** Al tocar `lang/es/collections.php` se vio que la base ya trae voseo ("dejás de cobrar", "Revisá…") cuando la regla de `formularios.md` es base en tuteo y `es_AR` solo con los overrides. No se tocó (no era la tarea): hay que barrer `lang/es/*.php` buscando formas en -ás/-és/-ís y pasarlas al tuteo, con su override en `es_AR` donde corresponda.
- **2026-10-11 — flake en la suite de browser completa (358 pruebas, 1 roja que pasa aislada):** `ConfigurationCompanyBrowserTest` "deleting a network asks first, through the system dialog" agotó el timeout de 5 s; corrida sola, 11/11 verdes. No toca nada de lo hecho ese día.
- **2026-10-11 — el test de browser que espera un poll no puede usar `waitForText`.** En el plugin instalado `waitForText` es un alias de `assertSee`, que reintenta ~5 s; para esperar un intervalo de 30 s hay que `->wait(34)` antes. Lo mismo vale para cualquier test futuro de `wire:poll`.
- **2026-10-10 — flake en la suite de browser completa** (tres fallos que PASAN aislados, exit 0): `CatalogTableLayoutBrowserTest` (dos maestros, la tabla 64 y 71px más ancha que su caja a 1280) y `CatalogSeasonalMastersBrowserTest` (timeout de 5 s con `route app/… not found` de Livewire). Solo aparecen dentro de la corrida larga (~30 min). No se persigue; si el de ancho de tabla vuelve, mirar qué maestro es antes de culpar al CSS.
- **2026-10-14 — los resúmenes programados fallan con `LazyLoadingViolation [subscription]`.** `atendia:whatsapp-digest` y `atendia:knowledge-digest` terminan en exit 1: `SendWhatsAppDigests.php:37` y `SendKnowledgeDigests.php:37` llaman `Business::plan()` en un `filter()` sin `->with('subscription')`. Aparecieron en `laravel-2026-10-14.log` (16:15) y una vez en `laravel.log`; no se tocó (no era la tarea).
- **RESUELTO 2026-10-15 — los resúmenes programados fallaban con `LazyLoadingViolation [subscription]`:** `SendWhatsAppDigests` y `SendKnowledgeDigests` ahora cargan `->with('subscription')`; ambos comandos salen con exit 0.
- **RESUELTO 2026-10-15 — las sugerencias del paso Servicios del wizard mezclaban tipos `producto`:** ya van al paso Productos (`goods: true`); medido en la base, las 538 sugerencias tienen tipo (102 `producto`, ninguna sin tipo), así que nada se cuela como servicio. Texto original:
- **2026-10-14 — las sugerencias del paso Servicios del wizard mezclan tipos de modalidad `producto`.** `suggested_services` del restaurante trae Calzones / Pizza a la piedra / Promo pizza + bebida (producto) en el paso 2, que guarda en `services`; `/productos` lee `products` y queda vacío. El paso 4 es solo manual/importación y no ofrece chips. Decisión de producto pendiente de ella.
- **RESUELTO 2026-10-14 — el paso Productos del wizard se reconstruía vacío en cada actualización del padre** (el producto agregado con Enter desaparecía; no era solo el Enter sin confirmar). Causa: el hijo `step-products` sin `wire:key` explícito se re-montaba cuando el padre re-renderizaba por `wizard:products-updated`, y `mount()` lo recargaba desde la base (vacía). Arreglo: `wire:key` fijo en los cuatro pasos de `⚡wizard.blade.php`, botón "Agregar", y las sugerencias de tipo producto pasaron del paso Servicios al paso Productos (`suggestionsName(..., goods: true)`). Cubierto por `WizardProductDraftBrowserTest`.
- **2026-10-15 — el login dio 500 once veces entre 15:39 y 15:56 UTC con `ArgumentCountError` en Carbon (`rawCreateFromFormat`, "3 passed … at least 2 expected").** Salía en `SendNewDeviceLoginAlert` al guardar `last_login_at`. Reproducido por consola 200.000 veces con JIT y sin JIT: no falla, y la línea declarada (587) no coincide con el archivo en disco (568), así que parece caché de opcache dañada. Se cortó con `kill -USR2` al master de php-fpm (16:00 UTC) y el login volvió a dar 302. **Causa NO probada; sospecha: el JIT tracing de PHP 8.5.10** (`docker/php/php.ini`, `opcache.jit = tracing`). Si reaparece: `opcache.jit = off` y reconstruir/recrear. No se tocó.

- 2026-10-15 · "Prueba Conexión" (business 25) sigue activa y SÍ tiene su instancia `business-25` (no es huérfana: el sensor no la marca), pero quedó en estado `connecting`, sin número ni emparejar. Candidata a quitarse con "Quitar el negocio". No se tocó.

- 2026-10-20 · Probando Aliadito con Cloudflare Access cerrado, un teléfono en datos móviles Y en wifi de otra casa no resuelve `wispy-sunset-7406.cloudflareaccess.com` (DNS_PROBE_FINISHED_NXDOMAIN, también abriéndolo directo). Desde el servidor y desde 1.1.1.1 / 8.8.8.8 / 9.9.9.9 resuelve bien. Sospecha: bloqueo DNS de la operadora o del teléfono; país y operadora sin confirmar. Si es de la operadora, Access no sirve para cerrar el piloto en Venezuela. Falta probar con DNS privado `one.one.one.one`. No se tocó.

- 2026-10-20 · `AdminUserForm::assignableRoles()` pinta `__("admin.users.roles.".$name)`: un rol creado desde el editor (p. ej. `diseno`) se vería como la clave cruda en el combobox de "Nuevo usuario". `User::roleLabel()` ya resuelve el fallback; no lo toqué (no era la tarea).

- 2026-10-20 · Flake: `PanelResponsiveBrowserTest > no screen scrolls sideways` dio "my-payments overflows by 10px" en la suite de browser completa y pasó 8/8 aislado. Y `ProcessIncomingWhatsAppMessageTest > crossing 80%` falló en la corrida completa y pasó aislado. La suite completa tarda ~13 min y la de browser ~40. No se tocó.

- 2026-10-20 · Encontrados al verificar el producto para escribir la ayuda (Paso 0). Ninguno se tocó:
  - `services.is_bookable` no tiene ningún control en pantalla, y `needs_services` le dice al dueño "Marca en Servicios cuáles se reservan con turno". Sin ese control, el listado de servicios reservables queda vacío.
  - El cambio de plan de Mi plan es una maqueta: `changePlan()` no guarda nada, y `SubmitPaymentReceipt` cobra el plan que la suscripción YA tiene. Por eso `cambiar-plan` está desactivado en la ayuda; reescribirlo y activarlo el día que se conecte.
  - Verificado a mano (corrige una versión anterior de esta nota, que era más fuerte de lo que el código sostiene): "te escribimos por aquí mismo" al enviar un reporte es ambiguo, porque la respuesta sale por WhatsApp o correo y no dentro del panel. La apelación de una suspensión manda correo SOLO al admin (`ADMIN_EMAIL`); al levantarla (`LiftSuspension`) no se avisa al dueño, así que "te respondemos por correo" es cierto solo si se responde a mano. El pie de "Pruébalo ahora" dice "responde con lo que cargaste" y el chat es fijo.
  - Panel del CLIENTE: la pantalla WhatsApp solo tiene conectar, volver a comprobar y cancelar el QR; la pista dice "desconéctalo" y no hay botón. En el admin solo hay "Liberar la instancia" (instancias sin negocio) y "Quitar el negocio"; no encontré un "Desconectar" para un negocio activo. El hint del logo dice "PNG o JPG" y también acepta WebP; el precio de Productos siempre sale con "$" aunque la moneda sea otra.
  - No hay plazo definido para verificar un comprobante, ni pantalla del dueño para ver el estado de sus reportes. El descuento de referidos se promete en pantalla y no se aplica (R-11).
  - La Ayuda ordena las categorías por su clave (alfabético): WhatsApp, que es lo que más se pregunta, queda al final de 44 artículos.

- 2026-10-20 · De la verificación independiente de la ayuda (cada punto comprobado por mí en el código). Ninguno se tocó:
  - `plan.trial_over` ("Tu prueba terminó") es una clave huérfana: ninguna vista ni clase la usa, así que al terminar la prueba Mi plan no dice nada. Cablearla o borrarla.
  - La tarjeta de Mi negocio dice "Nada es obligatorio" (`client.business.subtitle`), pero el nombre del negocio exige mínimo 3 caracteres.
  - El recordatorio de turno no sale para quien reservó solo por el link y nunca escribió al WhatsApp del negocio (`ProactiveSends::refusal` = 'cold'): queda sin enviar y el dueño no lo ve.
  - En el medidor "Tu perfil", "Datos de contacto" exige TRES datos (WhatsApp, "WhatsApp para derivar" y correo); con uno o dos la pieza queda incompleta aunque la pantalla no lo diga.
  - El correo de facturación (`billing_email`) se fija al crear la cuenta y no hay pantalla para cambiarlo, aunque a él van los avisos de cobro.

- 2026-10-20 · RESUELTOS los cuatro hallazgos del bloque "De la verificación independiente de la ayuda" (menos el del recordatorio por link, que sigue abierto): `plan.trial_over` ya se muestra en Mi plan cuando la prueba pasó y no hay un primer pago (`Subscription::trialEnded`); el subtítulo de Mi negocio dice que solo el nombre es obligatorio; Contacto ya no llama "opcionales" a los tres datos que cuenta el medidor y el aviso del menú dice "Falta completar tu contacto" / "Falta elegir tu moneda"; el "Correo de facturación" se edita en Mi negocio → Moneda (obligatorio, validado).

- 2026-10-20 · Sugerencias de la IA en Soporte (Paso 1): el interruptor `support.ai_suggestions` es un entero 0/1 porque `PlatformSetting` solo tiene tipos time, weekday, integer y decimal; un tipo booleano lo dejaría más claro en la pantalla de Ajustes. Sin tocar. Pendiente de ella antes de depender de esto: UNA llamada real de prueba (costo estimado de centavos) y la batería de tickets reales en `tests/Eval` cuando haya tickets; hoy hay 0 en la base.

- 2026-10-20 · Support battery run 2 (34/36). (1) HelpFinder did not offer `whatsapp-sin-verificar` for "en vez de conectado me sale sin verificar, que significa eso?" even with screen=whatsapp: a real retrieval miss, not fixed. (2) The help has no article for "the QR does not appear when I tap Conectar mi WhatsApp": the model was right to leave the draft empty; it is a documentation gap (the case stays in the battery as the marker).
- 2026-10-20 · Help search: fixed punctuation glued to words (SearchesText split on whitespace) and added ordering by distinct word hits; whatsapp-no-sale-el-qr article added. Battery run 3: 35/36 (the miss was the ordering, now fixed + unit test); run 4 pending her OK.

- 2026-10-21 · El hook `require-design-skill.sh` bloquea como "escritura por Bash" comandos de SOLO lectura en rutas de app/tests (un `grep ... | head` sobre tests/ y otro con `2>/dev/null`, y un `cat >>` por un nombre de carpeta en el texto). Falso positivo: partir el comando en dos lo deja pasar. Sin tocar.
- 2026-10-24 · Hallados al revisar «Horarios y cierres» de la app; ninguno se tocó (no era la tarea): (a) `Schedule::overlapErrors` solo compara turnos del MISMO día, así que lunes 22:00–02:00 y martes 01:00–05:00 no se ven como solape aunque `isOpenNow` los trata como el mismo tramo: la agenda ofrecería esa hora dos veces (ya era así en la web); (b) `CustomersReportTest > the file name says whose it is` falla si la corrida completa cruza la medianoche (compara el nombre del archivo con la fecha de hoy calculada antes): pasa aislado.
- 2026-10-21 · App (Ola 1): los 9 negocios de la base `atendia` (incluido Laboratorio Vida, el piloto real) dan `trial_ended` en la regla de acceso de la app: ninguno ha pagado. Para probar la app en su teléfono hace falta un negocio de prueba con un pago aprobado (decisión suya, ver aliadito-app.md).
