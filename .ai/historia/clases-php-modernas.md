# Clases PHP modernas — getters como property hooks (regla de oro)

> El stack corre PHP 8.5: en las clases PHP **puras** del proyecto un getter
> computado sin argumentos se escribe como **property hook** (PHP 8.4+), nunca
> como método. Nació el 2026-09-11 con la migración de las piezas de `Client`.
> Un tema por archivo: acá vive el *cómo* de los getters; el enforcement de
> 3 capas en `reglas-de-oro-enforcement.md`.

Esta regla está **blindada**: el test guardián
`tests/Feature/GoldenRulesPropertyHooksTest.php` y el hook
`check-php-getter-golden-rules.sh` fallan si aparece un getter-método en el
alcance. Sin allowlist: nació en cero y en cero se queda.

## La regla

- **Getter computado sin argumentos** → property hook, y el llamador lee una
  propiedad:

  ```php
  public Collection $links {
      get => $this->business->socialLinks;
  }

  // Caller: $socialMedia->links (sin paréntesis)
  ```

- **Accesor pasa-manos de un valor del constructor** → propiedad promovida
  `public readonly`, sin método ni hook (así quedó `Client`):

  ```php
  public function __construct(public readonly PersonalData $personalData) {}
  ```

- **Siguen siendo métodos**: conversiones `to…`/`from…` (`toArray`,
  `toPayload`, `toLivewire`), mágicos `__*`, factories estáticas (`for()`,
  `fromArray()`) y todo lo que reciba argumentos o haga trabajo (`save()`,
  `handle()`).

- **Visibilidad asimétrica (`private(set)`)** — criterio, no blindada: si una
  propiedad debe leerse desde afuera pero SOLO la clase la escribe, se declara
  `public private(set) Tipo $x;` en vez de esconderla tras un getter o dejarla
  `public` a secas. `readonly` sigue siendo la primera opción para lo que se
  fija una vez en el constructor; `private(set)` es para lo que la clase
  **re-escribe durante su vida** (contadores, estado interno que muta):

  ```php
  public private(set) int $attempts = 0;   // fuera: se lee; dentro: $this->attempts++
  ```

## Alcance (dónde aplica y dónde NO)

- **Aplica**: `app/Classes/` y `app/Dto/` — clases PHP puras, sin magia de
  framework en el medio.
- **NO aplica — no forzar hooks ahí**:
  - **Modelos Eloquent**: los atributos pasan por el `__get` mágico y los
    casts/`Attribute` de Laravel; un hook choca con esa magia. Relaciones,
    scopes y métodos de dominio (`options()`, `phoneFlags()`) siguen como están.
  - **Livewire (componentes y Forms)**: sus propiedades públicas son estado
    que se serializa/hidrata; una propiedad virtual no tiene valor de respaldo.
    Las computadas ahí van con `#[Computed]`.
  - **Actions y servicios**: hacen trabajo, son métodos.

## Trampas conocidas

- **`readonly` y hooks no se mezclan**: una propiedad hooked no puede ser
  readonly. Si la clase era `readonly class`, se baja el `readonly` a cada
  propiedad promovida y la hooked queda sin él (así quedó `RetrievedChunkDto`).
- Un hook `get` sin `set` deja la propiedad de **solo lectura** (escribirla
  tira `Error`) — es exactamente lo que quiere un getter.
- El hook se evalúa en **cada acceso**: no metas ahí trabajo caro sin memoizar
  (las relaciones Eloquent ya memoizan solas).
- El PHPDoc del tipo va como `@var` en la propiedad, no `@return`.

## Checklist de salida

- [ ] ¿Getter nuevo sin argumentos en `app/Classes`/`app/Dto`? → property hook.
- [ ] ¿Accesor pasa-manos? → `public readonly` promovida, sin método.
- [ ] ¿Se lee desde afuera pero solo la clase la escribe y muta? → `private(set)`.
- [ ] Los llamadores leen la propiedad (sin `()`); Blade tocado → `view:clear`.
- [ ] `./vendor/bin/pest --filter=GoldenRulesPropertyHooks` en verde.
