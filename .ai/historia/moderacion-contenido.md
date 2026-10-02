# Moderación de contenido — nada de lo que sube un negocio se saltea (regla de oro)

> Orden de la dueña (2026-09-20, construido 2026-09-27): un registro puede ser
> una FACHADA. Todo lo que un negocio sube o escribe pasa por moderación; lo
> grave suspende el negocio al instante. Enforcement de 3 capas en
> `reglas-de-oro-enforcement.md`; lo legal pendiente, en `aproduccion.md`.

Blindada: `tests/Feature/GoldenRulesUploadModerationTest.php` + hook
`check-upload-moderation-golden-rules.sh` (mismos patrones, tocar de a dos).

## Cómo funciona

- **Imágenes** (logo, foto de perfil, comprobante, y lo que venga): se validan
  SOLO con `AttributeValidator::imageUpload('origen', $requerido, $maxKb)` →
  PNG/JPG/WebP (SVG y PDF NO: la moderación no ve adentro) + regla
  `SafeUpload`, que revisa ANTES de guardar.
- **Textos** (perfil, servicios, productos, respuestas enseñadas, importaciones):
  todos terminan en `knowledge_documents`; el observer despacha
  `ModerateKnowledgeDocument` junto al indexado.
- **Niveles** (`ContentModeration`, OpenAI omni-moderation, gratis):
  menores o adulto ≥ `atendia.moderation.severe_score` → **suspende**;
  adulto por debajo → **se rechaza** y revisa el admin (la lencería puede
  dispararlo); sin respuesta → **no entra** (falla CERRADA, al revés que el
  guardián de WhatsApp del cliente final).
- **Suspensión** (`SuspendBusiness`): `businesses.suspended_at`; la IA calla,
  `canMessageCustomers()` corta todo envío, banner en el panel, correo +
  WhatsApp al equipo. Solo el admin la levanta en `/admin/moderacion`.
- **Evidencia**: solo la huella SHA-256 en `moderation_flags`, nunca el archivo.
- **Punto ciego**: OpenAI juzga menores SOLO en texto. Para imágenes falta
  Cloudflare CSAM Scanning Tool o PhotoDNA (pendiente, ver `aproduccion.md`).

## Checklist de salida

- [ ] ¿Subida nueva de imagen de un negocio? → `AttributeValidator::imageUpload`.
- [ ] ¿Texto nuevo que escribe un negocio? → que termine en un knowledge document.
- [ ] ¿Envío nuevo a clientes? → chequea `canMessageCustomers()`.
- [ ] `./vendor/bin/pest --filter="GoldenRulesUploadModeration|ContentModeration"` en verde.
