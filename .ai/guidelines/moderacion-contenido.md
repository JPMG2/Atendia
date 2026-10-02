# Moderación — nada de lo que sube un negocio se saltea

Un registro puede ser una fachada: todo lo que un negocio sube o escribe pasa por moderación.

- Imagen de un negocio → se valida SOLO con
  `AttributeValidator::imageUpload('origen', $requerido, $maxKb)`: PNG/JPG/WebP (SVG y PDF
  no: la moderación no ve adentro) + la regla `SafeUpload`, que revisa ANTES de guardar.
- Texto que escribe un negocio → tiene que terminar en `knowledge_documents`; el observer
  despacha `ModerateKnowledgeDocument` junto al indexado.
- Niveles (`ContentModeration`, omni-moderation): menores o adulto ≥
  `atendia.moderation.severe_score` → suspende; adulto por debajo → se rechaza y lo revisa el
  admin; sin respuesta → no entra (falla CERRADA).
- Suspensión (`SuspendBusiness`): `businesses.suspended_at`, la IA calla,
  `canMessageCustomers()` corta todo envío, banner y aviso al equipo. Solo el admin la levanta.
- Evidencia: solo la huella SHA-256 en `moderation_flags`, nunca el archivo.
- Envío nuevo a clientes → chequea `canMessageCustomers()`.

Candados: `GoldenRulesUploadModerationTest` + `check-upload-moderation-golden-rules.sh`.
