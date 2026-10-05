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

- **La suite entera no se corría desde el 2026-10-03 y escondía 4 rojos reales** (18 rojos en
  total: 12 eran flake y pasaron aislados). Los 4 vienen del commit `3600753`, no del trabajo
  de hoy:
  - 3 tests (`ConfigurationCompany`, `ConfigurationLogs`, `ConfigurationIntegrations`) buscaban
    el ítem de menú `menu.admin_settings`, que ese commit renombró a `menu.admin_platform`.
    **Arreglado hoy** (una línea cada uno).
  - `ConfigurationIntegrationsTest > the dashboard tile…` exige un tile en `/admin` que lleve a
    `admin.integrations`, y ese commit sacó la rejilla de tiles a propósito ("sus áreas ya
    viven en el menú"). **Sin tocar**: re-apuntarlo al menú o borrarlo cambia qué afirma el
    test, y eso lo decide ella. Hoy el ítem de menú ya está cubierto por el test de al lado.
- **`CatalogRegionTest > the country of every region is eager loaded` exige igualdad EXACTA de
  consultas y falla con 6 contra 5.** El N+1 que vigila NO existe: con 21 regiones corre MENOS
  consultas que con 1. Lo que rompe es que el primer render calienta un caché y el segundo ya
  no lo consulta. Falla aislado y sin que nadie toque el catálogo. El arreglo es
  `toBeLessThanOrEqual`, no una consulta menos.

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
