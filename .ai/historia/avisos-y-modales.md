# Avisos y modales — REGLA DE ORO: cero avisos nativos

> En AtendIa **no existe ningún aviso del navegador**. Ni uno. Nada de `alert()`,
> `confirm()` ni `prompt()`, ni en el panel admin ni en el del cliente. **Todos**
> los avisos, advertencias, confirmaciones y reintentos salen de la misma
> ventana del sistema.

Un tema por archivo: acá va el *cómo* del aviso. El sistema de diseño vive en el
skill `atendiadesign`, los formularios en `formularios.md`, y el enforcement de
3 capas en `reglas-de-oro-enforcement.md`. Enlazamos, no repetimos.

Esta regla está **blindada**: el test guardián `tests/Feature/GoldenRulesDialogTest.php`
y el hook `check-no-native-alerts.sh` fallan el build si aparece un aviso nativo.

## Por qué

- Un `confirm()` **no se puede tematizar**: rompe la marca, ignora los tokens, la
  tipografía y el modo oscuro. En el panel del cliente se lee como un error del
  sistema, no como parte del producto.
- Su texto lo escribe el **navegador**, no nosotros: los botones salen en el idioma
  del sistema operativo y se saltean las variantes regionales del español.
- **Bloquea el hilo**: nada se puede animar ni cancelar mientras está abierto, y en
  móvil algunos navegadores directamente lo suprimen — el aviso no aparece nunca.
- No se puede testear en el navegador: Playwright lo tiene que interceptar aparte.

## Cómo se avisa

La ventana la dibuja `<livewire:dialog />`, montada **una sola vez** en el layout
del dashboard (igual que el toast). Se abre con la función global `dialog.*`
(`resources/js/dialog.js`), que devuelve una **promesa**, así el que llama lee
como código normal:

```js
// Una pregunta. Devuelve true/false.
if (! await dialog.confirm({
    title: '¿Eliminar la red?',
    message: 'Se quita de la web y del pie de página.',
    accept: 'Eliminar',
    type: 'danger',        // info | success | warning (default) | danger
})) {
    return;
}

// Un aviso: un solo botón, no hay nada que decidir.
await dialog.notify({ title: 'Listo', message: '…', type: 'success' });

// Algo falló y se puede volver a intentar.
if (await dialog.retry({ title: 'No pudimos guardar', message: '…' })) { … }
```

- `type` elige el color y el glifo (tokens semánticos, dark/light solos).
- `accept` / `cancel` permiten **nombrar la acción** ("Eliminar la red") en vez de
  un "Aceptar" genérico: un botón que dice qué hace se entiende sin releer.
- Abrir un aviso **no cuesta un request**: es 100% Alpine.
- Escape, el click afuera y Cancelar son lo mismo: **no**. Nunca conviene que la
  vía de escape ejecute la acción.

## Aviso vs. toast — cuál va

- **Toast** (`HasNotifications` → `dispatchNotification()`): el resultado de algo
  que **ya pasó** y no requiere respuesta. "Compañía actualizada".
- **Diálogo** (`dialog.*`): cuando hace falta una **respuesta** antes de seguir, o
  cuando el aviso es tan importante que no puede pasar de largo.
- Si no hay nada que decidir y el usuario no necesita detenerse, es un toast.
  Un diálogo que solo informa algo trivial es una interrupción gratuita.

## Copy (se aplica lo de `formularios.md` §4)

- Todo el texto sale de traducciones (`__()`), base neutra en `lang/es/` y override
  de voseo en `lang/es_AR/` solo si el verbo cambia. Los rótulos por defecto de los
  botones viven en `lang/es/dialog.php`.
- **Título en pregunta** cuando hay que decidir ("¿Eliminar la red?"), y el mensaje
  dice la **consecuencia**, no repite el título. Sentence case, sin emoji.

## Checklist de salida

- [ ] Cero `alert()` / `confirm()` / `prompt()` (ni `window.*`) en Blade y en JS.
- [ ] El aviso sale por `dialog.*`; nada de una ventana propia hecha a mano.
- [ ] `type` acorde: `danger` **solo** para lo que no se deshace.
- [ ] El botón de acción nombra la acción; cancelar no ejecuta nada.
- [ ] Copy por traducciones, con la consecuencia en el mensaje.
- [ ] ¿Hacía falta detener al usuario? Si no, es un toast.
- [ ] Test Pest (en inglés) + `view:clear` y `npm run build` corridos.
