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
