# Hallazgos — lo que encontré mientras hacía otra cosa

> **El contrato (regla de trabajo 1).** Un hallazgo que NO es la tarea pedida se anota acá y
> **no se toca**. Al cerrar el turno se nombra en una línea y la dueña decide si entra.
> Perseguir cada hallazgo es lo que convierte una tarea de una hora en una mañana entera.
>
> Esto NO es la cola de ideas para enamorar (esas son mejoras ofrecidas, viven en la memoria
> `atendia-enamorar-cola`): acá van **defectos y deudas** encontrados de paso.
>
> Formato: una línea por hallazgo, con la fecha y dónde está. Cuando se arregla, se borra.

## 2026-10-05

- **Los 15 rojos de la corrida completa tienen UNA causa, y ya está diagnosticada.**
  `Company::current()` memoiza con `once()`, que guarda **para todo el PROCESO, no para el
  test**: una prueba que guarda una compañía deja a las siguientes leyendo esa fila. Por eso
  `SiteFooterCompanyTest` (8 rojos), `WelcomePageTest` (2) y compañía fallan juntos en la
  corrida entera y pasan aislados. No es mío ni lo amplifiqué: ya fallaba en la corrida de la
  mañana, antes de tocar `Company`.
  **El arreglo es `Once::flush()` en `TestCase::setUp()`** — lo probé y cierra la clase entera.
  Lo revertí porque destapa 4 tests de `ConfigurationCompanyTest` que pasaban JUSTAMENTE por
  la memoria rancia (esperan que no haya compañía y la hay). Entra como tarea propia: flush +
  ajustar esos 4, no a las apuradas antes de un commit.

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
