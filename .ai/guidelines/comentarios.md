# Comentarios y PHPDoc

Alcance: `app/`, `database/`, `tests/`, `routes/`, `config/`, `resources/js`,
`resources/views` (también el bloque PHP de un SFC) y `resources/css`.

- En inglés: comentarios, PHPDoc, mensajes de excepción y de log.
- Explican el PORQUÉ. Si describe lo que la línea de abajo ya dice, se borra.
- Máximo 3 líneas de `//` seguidas y 5 líneas de prosa en un docblock (los `@tag` no cuentan).
- Sin PHPDoc redundante. Sí lo que el tipo no expresa: array shapes, generics, `@throws`.
- Nada de código comentado ni banners decorativos.
- `lang/es*/` y el texto visible de las vistas siguen en español.
- `config/` no se juzga por largo (son archivos publicados por paquetes); el idioma sí.

Candado: `GoldenRulesCommentsTest` + `check-comment-golden-rules.sh`, con el MISMO
scanner (`tests/Support/CommentScanner.php`). La deuda congelada en `CommentScanner::FROZEN`
solo puede bajar.
