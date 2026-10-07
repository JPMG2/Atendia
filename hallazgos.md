# Hallazgos — lo que encontré mientras hacía otra cosa

> **El contrato (regla de trabajo 1).** Un hallazgo que NO es la tarea pedida se anota acá y
> **no se toca**. Al cerrar el turno se nombra en una línea y la dueña decide si entra.
> Perseguir cada hallazgo es lo que convierte una tarea de una hora en una mañana entera.
>
> Esto NO es la cola de ideas para enamorar (esas son mejoras ofrecidas, viven en la memoria
> `atendia-enamorar-cola`): acá van **defectos y deudas** encontrados de paso.
>
> Formato: una línea por hallazgo, con la fecha y dónde está. Cuando se arregla, se borra.

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

- **`Modelos de IA` (`admin/ai`) sigue armada con la pseudo-tabla inventada** (`aim-row`,
  `aim-task`, `aim-prices`, `aim-side`: `<span>` en flex con los lados en `flex:none`). Es la
  misma enfermedad que tenía `Consumo de IA`: cada fila calcula su ancho por su contenido, así
  que nada se alinea en vertical entre filas. El proyecto ya tiene `.pay-table` para esto.
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

- **`/admin/soporte` reparte mal el ancho a 900px**: la columna del ticket queda angosta y
  parte el texto en 8 líneas, mientras el selector de estado al lado es ancho y va casi vacío.
  Visto en `admin-tablet-light-admin-support.png`. Es anterior al movimiento de carpetas.


- **`PanelResponsiveBrowserTest` mide el desborde de la PÁGINA, no el de un contenedor.**
  Por eso da verde con la columna de arriba cortada. Ampliarlo a medir el `scrollWidth` de
  cada `.card`/tabla es una decisión de ella: es tocar el candado, no la pantalla.


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
- **`demo@atendia.test` aparece en `/admin/adopcion`** como una cuenta más que nunca pasó del
  wizard. Es el usuario de la demo local, no un negocio real; ensucia el embudo.
- **Un toast rojo falso en los browser tests que guardan el perfil del negocio**: la cola corre
  `sync` en pruebas, el job de conocimiento llama a OpenAI sin clave, falla, y `tryAction` lo
  convierte en "Registro no actualizado" — aunque el guardado funcionó. En producción la cola es
  Redis y no pasa. Entrena a ignorar errores en pantalla, igual que el 404 de Reverb.
- **No hay fila de Compañía en `atendia`** (`Company::current()` devuelve null): el pie de la
  landing y cualquier correo que la use caen al fallback.
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
