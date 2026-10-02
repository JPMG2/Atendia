# Reglas de oro — cómo se hace cumplir una

Una regla escrita no se cumple sola. Toda regla de oro tiene tres capas:

- **A · checklist** — el imperativo en su guía, con su checklist de salida.
- **B · guardián** — un test Pest (`tests/Feature/GoldenRules*`) que recorre el dominio y
  falla con el patrón prohibido. Es la capa permanente: cubre también a humanos.
- **C · hook** — `.claude/hooks/check-*.sh` en `PostToolUse Write|Edit`, que corrige en el
  acto. Si comparte patrón con el guardián se tocan de a dos; mejor: un **scanner
  compartido** (`tests/Support/*Scanner.php`), que no puede divergir.

## Reglas sobre las reglas

- Lo verificable por patrón va a B y C. Lo de criterio queda SOLO en el checklist.
- Las excepciones van en un allowlist con su razón escrita. Un ratchet solo BAJA.
- **Una regla nueva reemplaza a una existente, o se consolida.** El sistema no crece por
  acumulación: veinte imperativos de igual peso no son veinte candados, son ninguno.
- Un candado que no atrapó nada real en 60 días es candidato a borrarse.
- Un verificador que comparte criterio con el verificado no verifica nada. Sirve lo que
  mide el RENDER, la base o la pantalla; no las palabras de una respuesta.
