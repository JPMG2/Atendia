# Reglas de oro — cómo se hace cumplir una

## Los cuatro pilares (marco, decididos por ella)

Arriba de todo. Cada uno con lo que lo MIDE: un pilar sin medición es prosa.

1. **Ingeniería de contexto y RAG.** Lo que se carga en cada sesión es un presupuesto, no
   una pizarra. Una instrucción repetida vale MENOS, no más: el mismo imperativo en dos
   lugares es un imperativo en ninguno. Una regla vive en UN archivo; un pendiente, una
   tarea y una regla son tres cosas distintas y no se escriben en el mismo lugar.
   → **No tiene medición mecánica, y hay que decirlo:** ningún hook lee CUÁNTA instrucción
   hay ni en qué archivo quedó. Lo único que lo atrapa es ella frenando el turno. Por eso un
   candado nuevo acá es trampa: el 2026-10-03 inventé dos y el segundo se puso rojo contra sí
   mismo. La regla se cumple sin ayuda o no se cumple.
2. **Herramientas y protocolo.** Lo que puede ser una herramienta no se pide con palabras.
   Una regla verificable por patrón entra como hook y guardián (capas B y C), nunca como un
   párrafo más en una guía.
   → **Lo mide** `catches.sh`: qué atrapó cada candado, y cuál no atrapó nada.
3. **Agentes y flujo.** Una tarea por turno, con su condición de cierre. Lo que aparece y no
   es la tarea se APARCA en `hallazgos.md` y se nombra al cerrar; perseguirlo convierte una
   tarea de una hora en una mañana.
   → **Lo mide** `enforce-turn-exit.sh`: el turno no cierra con guardianes en rojo ni, si
   tocó vistas, sin evidencia visual real.
4. **Evaluación y constraints.** Nada se declara listo por su descripción. Un verificador
   que comparte criterio con el verificado no verifica nada: sirve lo que mide el render, la
   base, la pantalla o los bytes.
   → **Lo miden** los guardianes `GoldenRules*` y, para la IA, `tests/Eval`.

**La consecuencia que más cuesta cumplir:** el saldo de instrucción de un turno tiene que ser
CERO o NEGATIVO. Escribir una regla nueva sin borrar o consolidar otra es una regresión del
pilar 1, aunque la regla sea correcta.

---

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
