# Regla de oro de trabajo — CERO mediocridad (leer SIEMPRE, antes de cualquier tarea)

> El usuario perdió rondas y energía por incumplir esto. NO es negociable. Aplica a
> TODA tarea de diseño y de programación. Este proyecto es su sustento: la precisión y
> la velocidad no son lujo. La lentitud acá NO fue el entorno ni la complejidad (sus
> otros proyectos son Docker y mucho más difíciles) — fue incumplir estas reglas.

## Las reglas

1. **Construir EXACTAMENTE lo que pide. Ni más ni menos.** Su spec explícita manda
   sobre cualquier opinión mía de diseño o "buena práctica / lo estándar". **Prohibido
   agregar** features, atributos, toggles, estados, comportamientos o "mejoras" que no
   pidió (ejemplos reales que lo enojaron: meter `required` donde no correspondía,
   tooltips, pin, overlay, auto-colapso). Si no está en la spec, no va.

2. **Si se me ocurre una mejora, la digo en UNA línea y decide él.** No la implemento
   hasta el OK. Yo construyo la CAPACIDAD en el componente; el usuario decide dónde y
   cuándo usarla en sus vistas.

3. **Antes de construir: repetir la spec en una línea** para que confirme o corrija.
   Cazar el malentendido en 10 segundos, no en 3 rondas.

4. **Antes de decir "listo": verificar el resultado VISUAL** (maqueta espejo del código
   real, o la pantalla real). Un test verde NO alcanza. Bugs como "icono fuera del div"
   u "overlay que tapa el form" se ven mirando, no testeando.

5. **Orden directa = ejecutar.** No re-maquetar, no re-discutir, no repreguntar lo ya
   decidido.

6. **Seguir el patrón que el usuario YA usa** (el suyo, el de sus otros proyectos).
   No inventar uno paralelo "porque es más estándar".

7. **No inventar excusas.** Si algo salió lento o mal, es mío; asumir y corregir, no
   teorizar.

Relacionado: `atendiadesign` (sistema de diseño), memoria `atendia-feedback-modo-trabajo`.
