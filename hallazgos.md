# Hallazgos — lo que encontré mientras hacía otra cosa

> **El contrato (regla de trabajo 1).** Un hallazgo que NO es la tarea pedida se anota acá y
> **no se toca**. Al cerrar el turno se nombra en una línea y la dueña decide si entra.
> Perseguir cada hallazgo es lo que convierte una tarea de una hora en una mañana entera.
>
> Esto NO es la cola de ideas para enamorar (esas son mejoras ofrecidas, viven en la memoria
> `atendia-enamorar-cola`): acá van **defectos y deudas** encontrados de paso.
>
> Formato: una línea por hallazgo, con la fecha y dónde está. Cuando se arregla, se borra.

## 2026-10-02

- **3 rojos de browser bajo carga** (`CatalogSocialNetworkEdit`, `ClientResponsive`, `DemoChat`):
  pasan aislados, fallan en la corrida entera de 17 min. No se persiguen por orden de ella.
- **Solo 2 de 18 hooks comparten el scanner con su guardián** (comentarios y controles vivos).
  Hoy ninguno miente (probado 8 de 8), pero los otros 16 pueden divergir el día que cambie un criterio.
- **`resources/js/echo.js` tiene un comentario en español** (línea 19): la regla de comentarios
  alcanza a `resources/js`. Es deuda vieja, anterior a hoy.
- **La suite de browser cuesta 1052 s** (17,5 min) y es la puerta de cada commit que toca pantallas.
  Nadie lo había medido.
- **`demo@atendia.test` aparece en `/admin/adopcion`** como una cuenta más que nunca pasó del
  wizard. Es el usuario de la demo local, no un negocio real; ensucia el embudo.
- **No hay fila de Compañía en `atendia`** (`Company::current()` devuelve null): el pie de la
  landing y cualquier correo que la use caen al fallback.
