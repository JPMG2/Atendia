# AGENTS.md — instrucciones para Codex en AtendIa

> Este archivo es para **Codex**. El equivalente para Claude Code es `CLAUDE.md`
> (generado por Laravel Boost — **nunca editarlo a mano**, se regenera y se pierde).
> Las reglas de fondo son las mismas y viven en `.ai/guidelines/`: acá se resume lo
> obligatorio y se enlaza el detalle, no se duplica.

## 0. Dónde estás corriendo (leer primero)

Corrés **dentro del contenedor `atendia-app`**, en `/var/www/html`, que es un
bind-mount de `/var/www/atendia` en el host. Lo que tocás acá es el código real.

- Ejecutá `php artisan`, `composer`, `npm` y `./vendor/bin/pest` **directo**.
- ⚠️ `CLAUDE.md` y las guías de `.ai/guidelines/` están escritas para un agente que
  corre en el **host** y por eso prefijan todo con
  `docker exec -w /var/www/html atendia-app ...`. **Vos no**: sacale ese prefijo,
  acá no hay cliente de Docker.

## 1. 🚫 Pérdida de datos — regla dura, sin excepciones

La base de trabajo real es **`atendia`**. La única base de tests es **`atendia_testing`**.

**Nunca**, bajo ninguna circunstancia, contra `atendia`:

```
php artisan migrate:fresh · migrate:refresh · migrate:reset · db:wipe
```

Dropean tablas y **borran todos los datos**. Ya pasó una vez (2026-06-27) y costó caro.

**Importante:** Claude Code tiene un hook (`.claude/hooks/block-destructive-db.sh`) que
bloquea esos comandos. **Ese hook no corre para vos.** Acá la única red es que lo
cumplas vos.

Para aplicar una migración nueva, quirúrgico:

```
php artisan migrate --path=database/migrations/<archivo>.php
```

Detalle completo: `.ai/guidelines/migraciones-seguras.md`.

## 2. Cómo trabajar — cero mediocridad

Regla número uno del usuario, y la que más rondas costó:

1. **Construí exactamente lo que pide. Ni más ni menos.** Su spec manda sobre cualquier
   "buena práctica". Prohibido agregar features, atributos, toggles o "mejoras" que no
   pidió.
2. Si se te ocurre una mejora: **decila en una línea** y que decida él. No la implementes.
3. Antes de construir, **repetí la spec en una línea** para que confirme.
4. Antes de decir "listo", **verificá el resultado visual**. Un test verde no alcanza.
5. **Orden directa = ejecutar.** No re-discutir lo ya decidido.

Detalle: `.ai/guidelines/no-mediocre.md`.

## 3. Tests — Pest v4, en inglés, obligatorio

- **Siempre Pest v4**, nunca clases PHPUnit nuevas. Crear con
  `php artisan make:test --pest {Nombre}`.
- **Todo el testing en inglés**: descripciones de `test()`/`it()`, comentarios, nombres
  de archivo, helpers y datasets. (El código de la app y el copy de la UI van en español.)
- Correr: `./vendor/bin/pest --compact` (o con filtro/archivo). Todo cambio queda cubierto
  y **verde** antes de cerrar.
- Los tests corren sobre `atendia_testing` — forzado en `phpunit.xml` y con un guard en
  `tests/TestCase.php` que aborta si apuntás a otra base.

## 4. Formularios y Blade — reglas blindadas

Esto lo verifica un test guardián (`tests/Feature/GoldenRulesMarkupTest.php`), así que
si lo violás **la suite se pone roja**:

- Campos **solo** con `<x-ui.*>` / `<x-inputsform.*>`. Cero `<input>`, `<select>` o
  `<textarea>` crudos.
- **Cero hex de estilo** en el markup: todo color por token semántico de
  `resources/css/app.css`. (Excepción: un hex que es *dato* elegido por el usuario,
  aplicado vía variable.)
- Iconos solo `<x-icon name=".." :size=".." />`. Glifo nuevo → agregarlo antes a
  `config/icons.php`.
- Copy visible **vía traducciones** (`__()`), nunca hardcodeado: la app sirve variantes
  regionales de español (base `es` neutra + override `es_AR` con voseo).

Receta completa: `.ai/guidelines/formularios.md`. Sistema de diseño (marca jade+coral,
tipografía, tokens): `.claude/skills/atendiadesign/`.

## 5. Después de tocar código

- **PHP** → `./vendor/bin/pint --dirty` (Pint fuerza `declare(strict_types=1)`).
- **Blade o CSS** → `php artisan view:clear` **y** `npm run build`. Si no, la UI se ve
  rota o el foco de los inputs aparece doble: es CSS viejo, no un bug del componente.
- **composer install/update** → recargar php-fpm (`kill -USR2` al master) o el sitio tira
  502 por OPcache+JIT con bytecode viejo.

## 6. Git

- **Mensajes de commit en inglés**, imperativo y conciso (`Add UI form components`).
- `.env` y secretos **nunca** se commitean.
- Commitear solo cuando el usuario lo pida.

## 7. Guías completas (leerlas cuando toques ese dominio)

| Archivo | Tema |
| --- | --- |
| `.ai/guidelines/atendia.md` | Entorno, stack, convenciones generales, testing |
| `.ai/guidelines/no-mediocre.md` | Cómo trabajar (regla de oro del usuario) |
| `.ai/guidelines/migraciones-seguras.md` | Migraciones sin borrar datos |
| `.ai/guidelines/formularios.md` | Receta de cualquier formulario |
| `.ai/guidelines/arquitectura-paneles.md` | Paneles admin/cliente, roles y permisos |
| `.ai/guidelines/reglas-de-oro-enforcement.md` | Cómo se blinda una regla (3 capas) |
| `.ai/guidelines/documentacion-y-memoria.md` | Docs legibles, un tema por archivo |

## 8. Stack

Laravel 13 · PHP 8.5 · Livewire 4 (formato SFC por defecto) · Breeze (Blade) ·
Tailwind · Alpine (el que trae Livewire — **nunca** cargar Alpine aparte desde `app.js`) ·
Postgres con pgvector · Redis · Pest 4 · Pint · Rector.
