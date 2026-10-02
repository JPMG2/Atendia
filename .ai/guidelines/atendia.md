# AtendIa — núcleo

## Entorno
- El código vive en el host en `/var/www/atendia`, montado en `atendia-app:/var/www/html`.
- PHP, Composer, Artisan y npm corren DENTRO del contenedor:
  `docker exec -w /var/www/html atendia-app <comando>`. Un `timeout` va DESPUÉS del `docker exec`.
- Laravel 13 / PHP 8.5 · Breeze (Blade) · Livewire 4 · Postgres + Redis.
- `trustProxies(at: '*')` no se quita: detrás de Traefik, sin eso los assets salen en http.
- Tras tocar Blade o CSS: `view:clear` + `npm run build`, dentro del contenedor.

## Código
- Comentarios, PHPDoc, excepciones, logs y mensajes de commit: en INGLÉS (`comentarios.md`).
  Español SOLO en `lang/es*/` y el texto visible de las vistas.
- Tests en Pest v4 funcional, descripciones en inglés (`make:test --pest`, nunca `--phpunit`).
  Corren sobre `atendia_testing`; jamás sobre `atendia` (`migraciones-seguras.md`).
- Si se tocó PHP: `vendor/bin/pint --dirty --format agent` antes de cerrar.

## Estas guías
- Un tema por archivo, en imperativo, sin historia. El porqué, las fechas y los
  incidentes viven en `.ai/historia/` y NO entran al contexto: se leen a demanda.
- `CLAUDE.md` lo genera Boost desde esta carpeta. No se edita a mano.
- Un archivo grande no se rechaza: se lee por tramos, o se delega a un subagente que devuelve solo el resumen.
