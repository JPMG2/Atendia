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
