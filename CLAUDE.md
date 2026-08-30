<laravel-boost-guidelines>
=== .ai/arquitectura-paneles rules ===

# Arquitectura de paneles (admin / cliente)

> AtendIa tiene **2 paneles**: `admin` (configuración, el dueño) y `client` (el
> negocio cliente). Patrón "panels" (lo que formalizan Filament/Nova), nativo con
> Livewire + spatie. Esta guía evita improvisar al sumar áreas, features o roles.

## Identidad y acceso

- Roles spatie: `admin`, `client`. Permisos de ÁREA: `access-admin-panel`,
  `access-client-app` (los finos se suman por feature).
- **Super-admin**: `Gate::before` en `AppServiceProvider` → el rol `admin` pasa
  cualquier gate/policy (por eso ve también el panel cliente).
- El registro público asigna `client`. El rol `admin` SOLO por `AdminUserSeeder`
  (keyed a `ADMIN_EMAIL` en config), nunca por la web. Cambiar de admin = cambiar
  `ADMIN_EMAIL` y re-correr el seeder (degrada al anterior con syncRoles).

## Seguridad — NO negociable

- La cerradura va en **middleware de ruta** (`permission:...`) y/o **policies**,
  NUNCA en ocultar el menú. Ocultar un link es solo UX.
- Cada área nueva se cubre con **tests de acceso** (cliente↛admin = 403, etc.),
  como en `tests/Feature/PanelAccessTest.php`.

## Cómo sumar un área/feature

1. **Ruta**: si es del panel admin, va en `routes/admin.php` (ya tiene prefijo
   `/admin`, names `admin.*` y `permission:access-admin-panel` desde `bootstrap/app.php`).
   El panel cliente cuelga de `/dashboard` con `permission:access-client-app`.
   Para un permiso fino, agregá `->middleware('permission:loquesea')` a la ruta.
2. **Permiso nuevo**: definilo en `RolesAndPermissionsSeeder` y asignalo a los roles
   que correspondan (el admin pasa por super-admin igual). Resetear cache de permisos.
3. **Controllers/Livewire**: en namespaces por área (`App\Livewire\Admin\*`,
   `App\Livewire\App\*`) para que cada panel crezca aislado.
4. **Tests de acceso** sí o sí.

## Menú (data-driven, por panel + permiso)

- Tabla `menus`: columnas `panel` (admin|client) y `permission` (nullable).
- `Menu::tree($panel)` filtra por panel y por permiso (ítem visible si `permission`
  null o `auth()->user()->can()`; admin pasa por super-admin). Filtrado recursivo.
- `Navigation` (Livewire SFC) fija el panel en `mount()` con `request()->routeIs('admin.*')`.
- Sembrar ítems con su `panel` (cliente = default `client`). Iconos en `config/icons.php`.

## Switch de panel (admin)

- En el dropdown del topbar, gateado por `@can('access-admin-panel')`: alterna entre
  "Panel admin" y "Ver panel cliente". Badge "Admin" en el sidebar en `/admin`.

## Pendientes de diseño (cuando toque)

- **Impersonación**: "actuar como" un cliente puntual (ver sus datos). Feature aparte
  (package `lab404/laravel-impersonate` o propia), ligada a tenancy. Hoy solo está el
  acceso a la ESTRUCTURA del panel cliente (super-admin).
- **Tenancy**: aislamiento de datos por cliente (global scopes / `tenant_id` / policies).
  Capa aparte, compatible con esta arquitectura.

Memoria relacionada: `atendia-paneles-roles`. Receta de enforcement (tests/hooks):
`.ai/guidelines/reglas-de-oro-enforcement.md`.

=== .ai/atendia rules ===

# AtendIa — convenciones del proyecto

> ⚠️ ESTE archivo (y cualquier `.md`/`.blade.php` dentro de `.ai/guidelines/`) es tuyo.
> Laravel Boost lo INCLUYE en `CLAUDE.md` al correr `boost:install`/`boost:update`,
> pero NUNCA lo sobreescribe. Pon aquí todos tus prompts/instrucciones personalizados.
> Nunca edites `CLAUDE.md` a mano: Boost lo regenera y se perdería.

## Entorno (Docker)

- El código vive en el host en `/var/www/atendia`, montado en vivo dentro del contenedor `atendia-app` en `/var/www/html`.
- PHP, Composer y Artisan corren **dentro del contenedor**, no en el host. Para cualquier comando de Laravel usar:
  `docker exec -w /var/www/html atendia-app php artisan <comando>`
- Tras correr `composer`/`npm` como root en el contenedor, devolver permisos:
  `chown -R 1000:1000 /var/www/atendia` y `chown -R 82:82 storage bootstrap/cache`

## Stack

- Laravel 13 sobre PHP 8.5. Autenticación con Breeze (stack Blade). **Livewire 4** para componentes interactivos.
- BD Postgres y Redis compartidos vía la infra de EasyPanel (ver `.env`).

## Detrás de Traefik

- `bootstrap/app.php` usa `trustProxies(at: '*')`. No quitarlo: sin eso los assets se generan en http detrás del proxy https y se rompe el CSS.

## Testing — Pest OBLIGATORIO (override de la regla de Boost)

> ⚠️ Esto **anula** la regla de Boost que dice "usar PHPUnit y convertir Pest a PHPUnit".
> En este proyecto los tests se escriben **siempre en Pest v4**. Nada de clases PHPUnit nuevas.

- Framework: **Pest v4** (`pestphp/pest`) + plugin **`pestphp/pest-plugin-livewire`** para testear componentes Livewire (`livewire(Componente::class)->...`).
- Sintaxis funcional: `test('...', function () { ... })` / `it(...)` con `expect()`. El `TestCase` se enlaza en `tests/Pest.php`.
- **Todo el testing en INGLÉS:** descripciones de `test()`/`it()`, comentarios dentro de los tests, nombres de archivos, helpers y datasets van en inglés.

## Idioma del código — INGLÉS (regla de oro)

- **Comentarios, PHPDoc y mensajes de excepción/log van en inglés**, en TODO el
  proyecto (`app/`, `database/`, `tests/`, `routes/`, `config/`, los `{{-- --}}`
  de los Blade y `resources/js`). Cortos y explicando el PORQUÉ.
- **Lo único que sigue en español es lo que lee el cliente:** `lang/es*/*.php` y
  el texto visible de las vistas — tiene variantes regionales y no se toca.
- Regla completa, ejemplos y la lista de deuda: `.ai/guidelines/comentarios.md`.

## Git — mensajes de commit en INGLÉS

- **Todos los mensajes de commit van en inglés** (subject + body). Imperativo, conciso (ej. `Add UI form components`, `Fix icon size prop`).
- Identidad: `JPMG2 <jpmorenog22@gmail.com>`. Remote SSH: `git@github.com:JPMG2/Atendia.git` (rama `main`).
- `.env` y secretos NUNCA se commitean (ya cubierto por `.gitignore`).
- Crear tests con `php artisan make:test --pest {Nombre}` (NO `--phpunit`).
- Correr: `docker exec -w /var/www/html atendia-app ./vendor/bin/pest --compact` (o con un filtro/archivo). Cada cambio debe quedar cubierto y verde antes de cerrar.

### Base de datos de testing (Postgres real, producción blindada)

- Todo corre sobre **Postgres**, también los tests. Hay una base **dedicada** `atendia_testing` (owner `atendia_user`), separada de producción `atendia`.
- `phpunit.xml` fuerza `DB_CONNECTION=pgsql` + `DB_DATABASE=atendia_testing`; host/usuario/clave se heredan del `.env` (no se duplican secretos).
- **Blindaje:** `tests/TestCase.php` aborta cualquier test si el entorno no es `testing` o la base no es exactamente `atendia_testing`. **Jamás** apuntar los tests a `atendia`.
- La base `atendia_testing` se creó con el superusuario `laravel_user` del contenedor `ai_project_postgres-shared`:
  `CREATE DATABASE atendia_testing OWNER atendia_user;`

=== .ai/avisos-y-modales rules ===

# Avisos y modales — REGLA DE ORO: cero avisos nativos

> En AtendIa **no existe ningún aviso del navegador**. Ni uno. Nada de `alert()`,
> `confirm()` ni `prompt()`, ni en el panel admin ni en el del cliente. **Todos**
> los avisos, advertencias, confirmaciones y reintentos salen de la misma
> ventana del sistema.

Un tema por archivo: acá va el *cómo* del aviso. El sistema de diseño vive en el
skill `atendiadesign`, los formularios en `formularios.md`, y el enforcement de
3 capas en `reglas-de-oro-enforcement.md`. Enlazamos, no repetimos.

Esta regla está **blindada**: el test guardián `tests/Feature/GoldenRulesDialogTest.php`
y el hook `check-no-native-alerts.sh` fallan el build si aparece un aviso nativo.

## Por qué

- Un `confirm()` **no se puede tematizar**: rompe la marca, ignora los tokens, la
  tipografía y el modo oscuro. En el panel del cliente se lee como un error del
  sistema, no como parte del producto.
- Su texto lo escribe el **navegador**, no nosotros: los botones salen en el idioma
  del sistema operativo y se saltean las variantes regionales del español.
- **Bloquea el hilo**: nada se puede animar ni cancelar mientras está abierto, y en
  móvil algunos navegadores directamente lo suprimen — el aviso no aparece nunca.
- No se puede testear en el navegador: Playwright lo tiene que interceptar aparte.

## Cómo se avisa

La ventana la dibuja `<livewire:dialog />`, montada **una sola vez** en el layout
del dashboard (igual que el toast). Se abre con la función global `dialog.*`
(`resources/js/dialog.js`), que devuelve una **promesa**, así el que llama lee
como código normal:

```js
// Una pregunta. Devuelve true/false.
if (! await dialog.confirm({
    title: '¿Eliminar la red?',
    message: 'Se quita de la web y del pie de página.',
    accept: 'Eliminar',
    type: 'danger',        // info | success | warning (default) | danger
})) {
    return;
}

// Un aviso: un solo botón, no hay nada que decidir.
await dialog.notify({ title: 'Listo', message: '…', type: 'success' });

// Algo falló y se puede volver a intentar.
if (await dialog.retry({ title: 'No pudimos guardar', message: '…' })) { … }
```

- `type` elige el color y el glifo (tokens semánticos, dark/light solos).
- `accept` / `cancel` permiten **nombrar la acción** ("Eliminar la red") en vez de
  un "Aceptar" genérico: un botón que dice qué hace se entiende sin releer.
- Abrir un aviso **no cuesta un request**: es 100% Alpine.
- Escape, el click afuera y Cancelar son lo mismo: **no**. Nunca conviene que la
  vía de escape ejecute la acción.

## Aviso vs. toast — cuál va

- **Toast** (`HasNotifications` → `dispatchNotification()`): el resultado de algo
  que **ya pasó** y no requiere respuesta. "Compañía actualizada".
- **Diálogo** (`dialog.*`): cuando hace falta una **respuesta** antes de seguir, o
  cuando el aviso es tan importante que no puede pasar de largo.
- Si no hay nada que decidir y el usuario no necesita detenerse, es un toast.
  Un diálogo que solo informa algo trivial es una interrupción gratuita.

## Copy (se aplica lo de `formularios.md` §4)

- Todo el texto sale de traducciones (`__()`), base neutra en `lang/es/` y override
  de voseo en `lang/es_AR/` solo si el verbo cambia. Los rótulos por defecto de los
  botones viven en `lang/es/dialog.php`.
- **Título en pregunta** cuando hay que decidir ("¿Eliminar la red?"), y el mensaje
  dice la **consecuencia**, no repite el título. Sentence case, sin emoji.

## Checklist de salida

- [ ] Cero `alert()` / `confirm()` / `prompt()` (ni `window.*`) en Blade y en JS.
- [ ] El aviso sale por `dialog.*`; nada de una ventana propia hecha a mano.
- [ ] `type` acorde: `danger` **solo** para lo que no se deshace.
- [ ] El botón de acción nombra la acción; cancelar no ejecuta nada.
- [ ] Copy por traducciones, con la consecuencia en el mensaje.
- [ ] ¿Hacía falta detener al usuario? Si no, es un toast.
- [ ] Test Pest (en inglés) + `view:clear` y `npm run build` corridos.

=== .ai/comentarios rules ===

# Comentarios y PHPDoc — regla de oro

> Los comentarios de este proyecto se escriben **en inglés**, son **cortos** y
> explican **por qué**, nunca qué. Muchos comentarios no significan que esté todo
> bien: casi siempre significan que el código no se explica solo, o que alguien
> narró lo que la línea de abajo ya dice.

Esta regla está **blindada**: el test guardián `tests/Feature/GoldenRulesCommentsTest.php`
y el hook `check-comment-golden-rules.sh` fallan si un archivo la viola. La receta de
las 3 capas está en `reglas-de-oro-enforcement.md`.

## Las 5 reglas

1. **Inglés.** Comentarios, PHPDoc, y los mensajes de excepción y de log.
2. **El porqué, no el qué.** Si el comentario describe lo que el código ya dice,
   se borra. Sirve lo que el código NO puede contar: la razón, la trampa, el
   descarte.
3. **Corto.** Hasta **3 líneas** seguidas de `//`, y hasta **5 líneas de prosa**
   en un docblock (los `@tag` no cuentan). Si necesitás más, o el código está mal
   escrito, o eso es documentación y va a `.ai/guidelines/`.
4. **Cero PHPDoc redundante.** Nada de `@param string $name The name`. Solo lo
   que el tipo de PHP no puede expresar: array shapes, generics,
   `class-string<T>`, `@throws`.
5. **Nada de decoración ni código comentado.** Sin banners de sección, sin
   bloques comentados "por las dudas" — para eso está git.

## Qué NO alcanza esta regla

- **`lang/es*/*.php` y el texto visible de las vistas** siguen en **español**: es
  el copy que lee el cliente, y tiene variantes regionales. Ver
  `.ai/guidelines/formularios.md` §4.
- Los `.md` de `.ai/guidelines/` y las memorias siguen en español: los leés vos.
- Los banners `|-----|` que trae Laravel en `config/` son del framework.

## Ejemplos

```php
// ❌ narra lo que el código ya dice
// Guarda la compañía y devuelve la notificación
$company->save();

// ✅ cuenta lo que el código no puede
// The table holds a single row: without this fallback a second save would
// create a second company when the form mounted before the record existed.
$company = Company::query()->find($this->recordId) ?? Company::query()->first();
```

```php
// ❌ PHPDoc que repite la firma
/**
 * Send the message.
 *
 * @param  string  $locale  The locale
 */

// ✅ solo lo que el tipo no expresa
/** @param  array<int, string>  $recipients */
```

## La deuda (`tests/Feature/comment_debt.php`)

Cuando la regla nació había **229 archivos** con comentarios viejos. Están
congelados en esa lista, que es una **lista de pendientes, no una excepción**:

- El guardián solo tolera lo que está ahí; cualquier archivo fuera de la lista se
  exige completo.
- **Nunca se agrega un path.** Si un archivo nuevo falla, se arregla el archivo.
- Al limpiar un archivo hay que **sacarlo de la lista** — hay un test que falla si
  quedó adentro estando limpio, porque si no dejaría de estar protegido.
- La meta es que el archivo llegue a `[]` y se borre.

## Checklist de salida

- [ ] Todo comentario y PHPDoc en inglés.
- [ ] Ningún comentario describe lo que la línea de abajo ya dice.
- [ ] Ninguno pasa de 3 líneas (`//`) o 5 de prosa (docblock).
- [ ] Sin `@param` que repita el tipo; sí los array shapes y `@throws`.
- [ ] Sin código comentado ni banners decorativos.
- [ ] Si limpiaste un archivo, lo sacaste de `comment_debt.php`.
- [ ] `./vendor/bin/pest --filter=GoldenRulesComments` en verde.

=== .ai/documentacion-y-memoria rules ===

# Documentación y memoria — mantenerlas legibles

> Objetivo: que las guías y memorias **siempre se puedan leer**, sin que crezcan
> hasta el punto de "es demasiado grande, no la leo". Esa frase es un error: un
> archivo del proyecto nunca se rechaza.

## Reglas para guías (`.ai/guidelines/`) y docs del proyecto

- **Un tema por archivo.** Preferir varios archivos chicos y enfocados (p. ej.
  `api.md`, `frontend.md`) antes que un único archivo gigante.
- **Tabla de contenidos / secciones claras** al inicio de cada guía larga, para
  poder leer solo la parte que aplica.
- **Enlazar, no inflar.** Si un tema ya está cubierto en otra guía, enlazarlo en
  vez de repetir el contenido.

## Reglas para la memoria automática

- **Una idea por archivo de memoria.** Si una memoria se vuelve grande, partirla
  en varias y enlazarlas con `[[nombre]]`.
- **El índice `MEMORY.md`: una línea por entrada.** Nunca poner contenido en el
  índice, solo el puntero con un gancho corto.
- **Actualizar, no duplicar.** Si un dato cambia, editar la memoria existente; si
  quedó obsoleta, borrarla.

## Cómo leer archivos grandes (nunca rechazarlos)

Si un archivo es grande, NO negarse a leerlo. Usar una de estas vías:

- **Leerlo por tramos** (lectura parcial con offset/limit), no de una sola vez.
- **Delegarlo a un subagente** que lo lea entero y devuelva solo el resumen
  relevante, así el archivo grande no ocupa el contexto principal.

=== .ai/formularios rules ===

# Formularios — receta de calidad (regla de oro)

> Cómo se construye **cualquier** formulario en AtendIa para que salga conforme a la
> primera y pase el blindaje. Un tema por archivo: acá va el *cómo* del form; el
> sistema de diseño (marca, tokens, componentes) vive en el skill `atendiadesign`,
> la seguridad por panel en `arquitectura-paneles.md`, y el enforcement de 3 capas en
> `reglas-de-oro-enforcement.md`. Enlazamos, no repetimos.

Esta regla está **blindada**: el test guardián `tests/Feature/GoldenRulesMarkupTest.php`
y el hook `check-blade-golden-rules.sh` fallan el build si un form la viola. La guía te
evita llegar a ese error; el blindaje lo hace imposible de incumplir.

## Tabla de contenidos

1. Estructura (Livewire SFC)
2. Campos — SOLO `<x-ui.*>` (lo blindado)
3. Color, tipografía, iconos
4. Copy e i18n — el español se adapta a la región
5. Layout / UX (criterio, no blindado)
6. Cierre obligatorio (3 mandatos + test)
7. Checklist de salida

---

## 1. Estructura (Livewire SFC)

- Componente **SFC** por defecto (`resources/views/livewire/...`), no clase+vista
  separada. (Ver memoria `livewire-convenciones`; MFC/class solo por caso justificado.)
- Propiedades públicas **tipadas**, una por campo. `mount()` carga el estado inicial.
- `rules()` con validación **server-side** (Livewire valida como una request HTTP).
- `save()`: `validate()` → persistir → feedback (`$this->dispatch('...')` para el toast).
- **Autorización dentro de la acción** (policy / `can`), NUNCA solo ocultando el botón.
  Ocultar un control es UX; la cerradura va en middleware de ruta y/o policy.

## 2. Campos — SOLO componentes `<x-ui.*>` (esto es lo blindado)

- Usar **siempre** `<x-ui.input>`, `<x-ui.select>`, `<x-ui.textarea>`, `<x-ui.switch>`,
  `<x-ui.checkbox>`. **Cero** `<input>` / `<select>` / `<textarea>` crudos.
- El `error` se **autocablea por `name`** desde el ErrorBag: pasás `name="c.nombre"` y el
  campo muestra su error solo.
- **Foco = un solo anillo** (lo dibuja el wrapper `.field-control`). Si aparece un anillo
  doble o azul nativo → NO es el componente, es CSS sin recompilar → `npm run build`.
- ¿Falta un control (ej. file / dropzone)? **Primero se crea el `<x-ui.*>` con su test
  Pest**, recién después se usa. Nunca un control crudo "por excepción".

## 3. Color, tipografía, iconos

- **Cero hex en el markup.** Todo color por token semántico de `app.css`
  (`text-strong`, `bg-card`, `bg-brand-soft`, `var(--brand)`…). Ningún `#fff`/`#0EA47A`.
  - *Excepción legítima:* un hex que es **dato** que el usuario elige y se guarda (p. ej.
    color de marca en un maestro), aplicado vía **variable** (`style="color:{{ $valor }}"`),
    nunca un literal para estilar. Los swatches viven en PHP, no en Blade.
- Números / precios / teléfonos / IDs / códigos en **`font-mono`**; titulares `font-display`.
- Iconos **solo** `<x-icon name=".." :size=".." />` (16–20px en UI). Nunca `<i data-lucide>`.
  Si falta el glifo → agregarlo a `config/icons.php` **antes** de usarlo.

## 4. Copy e i18n — el español se adapta a la región

> ⚠️ El copy de un form **NO se escribe hardcodeado**. AtendIa sirve **variantes
> regionales de español** (no traducción a otros idiomas): la región de quien nos visita
> cambia el tuteo/voseo. Si escribís el texto fijo en el Blade, rompés esa adaptación.

- Todo texto visible (labels, hints, placeholders, botones, mensajes de éxito/error)
  sale de **archivos de traducción** con `__('...')` / `@lang`, no de strings sueltos.
- **Base neutra en `lang/es/`** = tuteo (*"Conecta tu WhatsApp", "por ti"*). Cubre VE, CO,
  MX, CL y el resto. Acá vive el copy completo del form.
- **`lang/es_AR/`** = SOLO overrides de **voseo** (*"Conectá", "por vos"*). Archivo
  parcial: lo que no está cae a `es`. Agregás override únicamente si hay voseo.
- **`lang/es_VE/`** reservado (tuteo igual que el neutro), para guiños léxicos puntuales.
- Depende de `fallback_locale=es`. La región se resuelve por el middleware `SetLocale`
  (sesión › geo IP › default) y el **selector manual** manda. Detalle completo en la
  memoria `atendia-i18n-variantes-regionales`.
- Estilo (aplica a las tres variantes): **sentence case**, verbo primero, concreto, **sin
  emoji** en la chrome. Errores útiles: *"No pudimos guardar. Revisá el email."*
  (neutro: *"Revisa el email."*).

## 5. Layout — aprovechar el ancho (REGLA DE ORO, blindada)

> Blindada por `tests/Feature/GoldenRulesFormLayoutTest.php` y el hook
> `check-catalog-form-layout.sh`. Nació de un formulario que gastaba media pantalla:
> cuatro campos angostos apilados y una fila entera para un switch.

### 5.1 Compactar sin abreviar

- **Nunca se abrevia ni se trunca un campo de un maestro.** Compactar es acomodar mejor,
  jamás recortar: si un campo no entra, se rearman las filas, no se achica el dato.
- El formulario **no topea su ancho**. Un `max-width` deja espacio muerto a la derecha
  del panel. Se usa todo el contenedor.

### 5.2 Las filas se DECLARAN, no las adivina el navegador

- Los campos van dentro de **`<x-catalog.form-row>`**. Si el corte lo decide el wrap,
  el mismo formulario cambia de forma según el monitor y el último campo —casi siempre
  el estado— cae solo a una fila entera.
- **Fila 1:** identificador corto + nombre, y el nombre se lleva todo el resto.
  **Fila 2:** el resto de los campos repartiéndose el ancho completo, **el estado incluido**.
- **Toda fila llega al borde derecho.** Al menos un campo de la fila tiene que poder
  absorber el sobrante (uno descriptivo); una fila hecha solo de códigos queda corta.

### 5.3 El ancho se declara por CONTENIDO, nunca en columnas

- Cada campo dice **qué es**, no cuánto mide: `span="code|short|text|long|full"`.
  Repartir a mano (`col-4` + `col-8`) es el origen del borde ragged — tarde o temprano
  un maestro elige spans que no suman.
- Los `flex-basis` viven en `app.css` (`.f-code`, `.f-short`, `.f-text`, `.f-long`).
  El sobrante se lo lleva el descriptivo; un código de 3 letras no crece.

### 5.4 El estado es un campo, no un bloque

- El switch `activo` usa **`<x-inputsform.switch-field>`**: misma caja y misma altura que
  un input, con la palabra del estado al lado. Entra en la fila como un control más.
  Gastar una fila entera del formulario en un booleano es desperdiciar la pantalla.

### 5.5 El error tiene que verse, y verse de quién es

- Cuando un campo muestra su error **crece**, y el mensaje queda flotando entre dos filas.
  La separación **entre filas** tiene que ser claramente mayor que la que hay entre un
  control y su propio mensaje; si no, el error se lee como si fuera del campo de abajo.
- No se reserva un renglón fijo bajo cada campo: eso mete aire muerto en todas las filas
  para un error que casi nunca está.

### 5.6 Cero markup repetido

- El chrome del maestro (toolbar, tabla, barra del form, pie de acciones) y el riel de
  Alpine viven **una sola vez**: `<x-catalog.*>` y `resources/js/catalog-master.js`.
  Copiarlo hace que un arreglo en un maestro no llegue a los otros.

### 5.7 Otros patrones (criterio, no blindado)

- **Orden lógico de los campos.** Se ordenan como los piensa quien carga el dato:
  identificador → nombre/descriptivo → atributos de formato/visualización → estado.
- Patrón útil: **2 columnas** (`1fr / ~340px`), form a la izquierda y **preview sticky** a
  la derecha; colapsa a 1 col en mobile. **Tabs** si hay muchas secciones.
- Filas "label + descripción a la izquierda / campos a la derecha", apiladas en mobile.
- **Reusar** `<x-ui.card>`, `<x-ui.button>`, `<x-ui.alert>`, `<x-ui.tabs>` — no reinventar
  markup. Revisá la librería `<x-ui.*>` antes de escribir algo nuevo.

## 6. Cierre obligatorio (3 mandatos + test)

- Los 3 mandatos de `atendiadesign`: **responsive mobile-first · claro/oscuro por tokens ·
  estilo desde `app.css`**. Probar ≤900px y ≤560px, y ambos temas.
- **Test Pest en inglés** que cubra render + la regla relevante. Correr la suite en verde
  antes de dar por terminado.
- Tras tocar Blade/CSS: `view:clear` + `npm run build` (si no, la UI se ve rota o el foco
  aparece doble). Ver memorias `frontend-build` y `blaze-rector`.

## 7. Checklist de salida (verificar uno por uno antes de cerrar)

- [ ] Campos 100% vía `<x-ui.*>` — cero controles crudos.
- [ ] **Orden lógico** de los campos (identificador → nombre → formato → estado).
- [ ] **Filas declaradas** con `<x-catalog.form-row>`; ninguna queda a medias.
- [ ] **Ancho por contenido** (`span=`), nunca `col-N`; el form no topea su ancho.
- [ ] **El estado es un campo** (`switch-field`), no una fila entera para un booleano.
- [ ] **Con error a la vista**: el mensaje se lee pegado a SU campo, no a la fila de abajo.
- [ ] Nada abreviado ni truncado.
- [ ] Cero hex de estilo en el markup (solo tokens; hex-dato vía variable si aplica).
- [ ] Iconos solo `<x-icon>`; glifos nuevos agregados a `config/icons.php`.
- [ ] Copy vía traducciones (`__()`), base `es` neutra + override `es_AR` si hay voseo.
- [ ] Autorización en la acción/policy, no solo ocultando el botón.
- [ ] Reusé componentes existentes antes de crear.
- [ ] Test Pest (en inglés) verde; `view:clear` + `npm run build` corridos.
- [ ] Responsive + claro/oscuro verificados.

=== .ai/migraciones-seguras rules ===

# Migraciones seguras — NUNCA borrar datos de `atendia`

> Incidente 2026-06-27: la base de trabajo `atendia` quedó sin datos. Regla dura
> para que sea imposible repetirlo. La base de producción/trabajo es **`atendia`**;
> la única base donde se testea es **`atendia_testing`**.

## Prohibido sobre `atendia`

- `php artisan migrate:fresh` · `migrate:refresh` · `migrate:reset` · `db:wipe`
  → dropean tablas y **borran todos los datos**. JAMÁS sobre `atendia`.

## Blindaje en 4 capas (por qué es imposible perder datos)

> Auditado el 2026-07-25 tras analizar cómo OTRO proyecto perdió toda su data: la
> protección nativa de Laravel estaba atada a `isProduction()` y, como el dato real
> vive en una base con `APP_ENV=local`, quedaba **apagada**. Acá lo cerramos.

1. **`DB::prohibitDestructiveCommands()` — la capa clave (nivel comando).** En
   `AppServiceProvider::configureCommands()`. **NO** se gatea por `app()->isProduction()`
   (agujero: `.env` es `APP_ENV=local` y el dato vive en `atendia` → daría `false` →
   protección apagada). Se gatea por la **base activa**: bloquea *siempre* salvo cuando
   la base es exactamente `atendia_testing`.
   ```php
   $connection = config('database.default');
   $database   = config("database.connections.{$connection}.database");
   DB::prohibitDestructiveCommands($database !== 'atendia_testing');
   ```
   Cubre el CLI directo **y** `RefreshDatabase` (que corre `migrate:fresh` por dentro),
   en Unit o Feature, **extienda o no** el guard de `TestCase`.
2. **Hook `PreToolUse`** `.claude/hooks/block-destructive-db.sh` (registrado en
   `.claude/settings.json`): bloquea (exit 2) cualquier Bash con esos comandos salvo que
   apunte explícitamente a `atendia_testing`. Ojo: el hook **no ve** `RefreshDatabase`
   (corre en PHP, no shell) → por eso la capa 1 es imprescindible.
3. **Guard `Tests\TestCase::guardAgainstProductionDatabase()`**: aborta si el entorno no
   es `testing` o la base no es `atendia_testing`. Agujero conocido: en `tests/Pest.php`
   se bindea SOLO a `Feature`+`Browser`, **no a `Unit`** — hoy inocuo porque la capa 1
   ya cubre Unit.
4. **`phpunit.xml`** fuerza `DB_CONNECTION=pgsql` + `DB_DATABASE=atendia_testing`.

Blindado con test de regresión: `tests/Feature/DestructiveCommandGuardTest.php` prueba
(seguro, con base descartable) que los 4 comandos quedan `prohibited` cuando la base ≠
`atendia_testing`.

## Cómo aplicar migraciones a `atendia`

- **Solo las pendientes:** `php artisan migrate` (no toca tablas ya migradas, no borra data).
- **Quirúrgico (preferido al aplicar UNA nueva):**
  `php artisan migrate --path=database/migrations/<archivo>.php`
  → corre **únicamente esa** migración. Ver convención del usuario.
- Recordá el entorno Docker: `docker exec -w /var/www/html atendia-app php artisan migrate ...`

## Al crear una migración nueva (flujo completo)

1. Crear la migración (y modelo/seeder).
2. **Aplicarla a `atendia` con `migrate --path`** — si no, la feature no existe en el
   sitio real (la tabla solo viviría en `atendia_testing` vía tests).
3. Si corresponde, sembrar datos: `php artisan db:seed --class=<Seeder>` (no usa fresh).
4. Devolver permisos tras correr como root en el contenedor.

## Testing

- Los tests reales usan **RefreshDatabase** sobre `atendia_testing` (forzado en
  `phpunit.xml` + guard en `tests/TestCase.php`). No se usan los comandos `migrate:*`
  destructivos a mano para testear.

Relacionado: la receta de enforcement de 3 capas — ver `.ai/guidelines/reglas-de-oro-enforcement.md`.

=== .ai/no-mediocre rules ===

# Regla de oro de trabajo — CERO mediocridad (leer SIEMPRE, antes de cualquier tarea)

> El usuario perdió rondas y energía por incumplir esto. NO es negociable. Aplica a
> TODA tarea de diseño y de programación. Este proyecto es su sustento: la precisión y
> la velocidad no son lujo. La lentitud acá NO fue el entorno ni la complejidad (sus
> otros proyectos son Docker y mucho más difíciles) — fue incumplir estas reglas.

## Las reglas

1. **Construir EXACTAMENTE lo que pide. Ni más ni menos.** Su spec explícita manda
   sobre cualquier opinión mía de diseño o "buena práctica / lo estándar". **Prohibido
   agregar** features, atributos, toggles, estados, comportamientos o "mejoras" que no
   pidió (ejemplos reales que lo enojaron: meter `required` donde no correspondía,
   tooltips, pin, overlay, auto-colapso). Si no está en la spec, no va.

2. **Si se me ocurre una mejora, la digo en UNA línea y decide él.** No la implemento
   hasta el OK. Yo construyo la CAPACIDAD en el componente; el usuario decide dónde y
   cuándo usarla en sus vistas.

3. **Antes de construir: repetir la spec en una línea** para que confirme o corrija.
   Cazar el malentendido en 10 segundos, no en 3 rondas.

4. **Antes de decir "listo": verificar el resultado VISUAL** (maqueta espejo del código
   real, o la pantalla real). Un test verde NO alcanza. Bugs como "icono fuera del div"
   u "overlay que tapa el form" se ven mirando, no testeando.

5. **Orden directa = ejecutar.** No re-maquetar, no re-discutir, no repreguntar lo ya
   decidido.

6. **Seguir el patrón que el usuario YA usa** (el suyo, el de sus otros proyectos).
   No inventar uno paralelo "porque es más estándar".

7. **No inventar excusas.** Si algo salió lento o mal, es mío; asumir y corregir, no
   teorizar.

Relacionado: `atendiadesign` (sistema de diseño), memoria `atendia-feedback-modo-trabajo`.

=== .ai/reglas-de-oro-enforcement rules ===

# Reglas de oro — receta de enforcement (convención del proyecto)

> Para que una "regla de oro" se cumpla **a rajatabla** no alcanza con escribirla:
> una guía es contexto pasivo y se puede pasar por alto. La garantía real la da
> una verificación **determinística** que corre la herramienta, no el modelo.

Toda regla de oro de este proyecto se implementa con **3 capas**. Las dos primeras
hacen que casi siempre salga bien; la tercera lo hace **imposible de incumplir**.

## Capa A — Skill con checklist de salida

- Las reglas viven en un **skill** con `description` (trigger) inequívoca que
  active al entrar al dominio (p. ej. "usar SIEMPRE al crear un formulario o un
  componente Livewire").
- El skill **termina con un checklist explícito** que debo verificar **antes de
  dar la tarea por terminada**. Convierte la regla en un paso de salida, no en un
  buen deseo.

## Capa B — Garantía determinística (SIEMPRE, si es regla de oro)

Elegí la herramienta según el dominio:
- **PHP (modelos, clases)** → **arch test de Pest** (`arch()`), hecho para esto.
- **Migraciones / markup Blade** → **test guardián**: un test Pest que recorre los
  archivos del dominio y **falla** si encuentra un patrón prohibido.
- Ambos corren en la suite / CI → protegen también ediciones de humanos u otras
  herramientas. Es la red permanente.

## Capa C — Hook `PostToolUse` (corrección instantánea)

- Un hook en `.claude/settings.json` que matchea `Write|Edit` sobre los archivos
  del dominio, valida el archivo recién escrito y, si viola algo, **devuelve el
  error en el momento** (exit 2) para corregir antes de que corran los tests.

## Cómo se clasifica cada regla

Cuando se suma un set de reglas de oro:
1. Separar cada regla en **verificable por patrón** (va a capa B y C) vs **de
   criterio/UX** (queda solo en el checklist del skill, capa A).
2. Definir un **allowlist de excepciones** explícito y comentado para no generar
   falsos positivos (primitivos, casos legítimos, deuda pre-existente).
3. **Ratchet:** si hay incumplimientos previos que no se arreglan ahora, se
   congelan en el allowlist con su razón — **nunca se agrega nada nuevo a esa
   lista**, se arregla.

## Implementaciones vivas (ejemplos de esta receta)

- **Formularios / markup** → checklist en skill `atendiadesign` · test guardián
  `tests/Feature/GoldenRulesMarkupTest.php` · hook
  `.claude/hooks/check-blade-golden-rules.sh`.
- **No perseguir un flake** → memoria `atendia-feedback-modo-trabajo` (capa A) ·
  hook `.claude/hooks/block-browser-suite-reruns.sh` (capa C), que bloquea la
  TERCERA corrida de la suite de browser entera. Escrito ya había estado y se
  incumplió igual varios días seguidos: por eso hay hook.
- **Formularios / layout (aprovechar el ancho)** → `.ai/guidelines/formularios.md` §5 +
  checklist del skill · test guardián `tests/Feature/GoldenRulesFormLayoutTest.php` ·
  hook `.claude/hooks/check-catalog-form-layout.sh`.
- **Comentarios / PHPDoc en inglés y cortos** → `.ai/guidelines/comentarios.md` ·
  test guardián `tests/Feature/GoldenRulesCommentsTest.php` · hook
  `.claude/hooks/check-comment-golden-rules.sh`. Las capas B y C comparten el
  MISMO scanner (`tests/Support/CommentScanner.php`), así que no pueden divergir
  —a diferencia de los allowlists espejados de la regla de markup, que hay que
  tocar de a dos—. Ratchet en `tests/Feature/comment_debt.php`.
- **Migraciones / modelos** → *(pendiente: skill propio + `arch()` para modelos +
  test guardián para migraciones cuando se sumen las reglas).*

Ver también: [[documentacion-y-memoria]] (un tema por archivo, legibilidad).

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
