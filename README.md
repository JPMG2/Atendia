# Atendia

**Tu negocio, atendido por IA.** Atendia es un SaaS multi-tenant que le da a un
negocio chico —un laboratorio, una peluquería, un comercio— un asistente de IA
que atiende su WhatsApp las 24 horas: responde con el catálogo real del negocio,
en el idioma del cliente, con honestidad ante lo que no sabe, y construye de
paso el activo más valioso del negocio: su directorio de clientes.

## Qué hace hoy

- **Asistente de WhatsApp con RAG propio**: responde solo con la base de
  conocimiento del negocio (servicios, productos, precios, ficha) vía embeddings
  + pgvector; si la búsqueda no lo confirma, lo dice y ofrece derivar.
- **Onboarding guiado**: registro → wizard del negocio → catálogo (carga manual
  o import Excel/CSV con mapeo asistido por IA) → simulador en vivo → conexión
  del número por QR.
- **Conversaciones en vivo**: bandeja por tenant con Reverb (WebSockets),
  búsqueda literal y semántica, filtro por fechas, historial por tramos.
- **Clientes (CRM)**: ficha auto-creada desde el primer mensaje; la IA captura
  datos reales (nombre, correo, cumpleaños) con confianza y procedencia, sin
  pisar jamás lo escrito por un humano; opt-in de marketing explícito.
- **Planes y uso**: reverse trial de 14 días, entitlements por plan como fuente
  única, medidores de uso, programa de referidos con QR.
- **Estadísticas**: KPIs con lectura en una frase, tendencias, horas pico y
  detección de temas que el catálogo no cubre (clustering de embeddings).

## Stack

Laravel 13 · PHP 8.5 · Livewire 4 (SFC + islands) · Alpine.js · Tailwind 3 ·
PostgreSQL + pgvector · Redis · Reverb · laravel/ai (OpenAI) · Evolution API
(WhatsApp) · Pest 4 (incluye browser testing con Playwright).

## Decisiones de arquitectura que importan

- **Tenancy en dos cinturones**: global scope `BelongsToBusiness` + **Row-Level
  Security de Postgres** (política por `app.current_tenant`, FORCE). Un tenant
  no alcanza a otro ni con SQL crudo.
- **Reglas de oro blindadas por 3 capas** (guía + test guardián + hook): campos
  solo por componentes de la casa, cero avisos nativos, validación en Forms,
  queries jamás en Blade, comentarios en inglés con el porqué, fechas siempre
  con Flatpickr, correo solo por `app/Messaging`.
- **Sistema de diseño con tokens semánticos** (jade + coral, Sora/Jakarta/
  JetBrains Mono), claro/oscuro completo, español regional (`es` neutro con
  overrides `es_AR` de voseo).
- **La IA como capa, no como caja negra**: instrucciones interpoladas por
  negocio, herramientas pinneadas al tenant en construcción (el modelo nunca
  elige el tenant), costo medido por intercambio.

## Desarrollo

El entorno vive en Docker (`atendia-app`); PHP, Composer, npm y Artisan corren
dentro del contenedor:

```bash
docker exec -w /var/www/html atendia-app php artisan migrate
docker exec -w /var/www/html atendia-app npm run build
docker exec -w /var/www/html atendia-app ./vendor/bin/pest --compact
```

Los tests (1500+) corren sobre una base Postgres dedicada (`atendia_testing`),
con guardas que impiden tocar la base de trabajo. Los browser tests son
on-demand: `./vendor/bin/pest tests/Browser/... ` como `www-data`.

---

Proyecto privado. © Atendia.
