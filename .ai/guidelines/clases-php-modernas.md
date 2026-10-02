# Clases PHP puras — getters como property hooks

Alcance: `app/Classes/` y `app/Dto/`. NO aplica a Eloquent, Livewire, Actions ni servicios.

- Getter computado sin argumentos → **property hook**, y el llamador lee la propiedad:
  `public Collection $links { get => $this->business->socialLinks; }`.
- Accesor pasa-manos de un valor del constructor → propiedad promovida `public readonly`.
- Siguen siendo métodos: `to…`/`from…`, mágicos, factories estáticas y todo lo que reciba
  argumentos o haga trabajo.
- Se lee desde afuera pero solo la clase la escribe y muta → `public private(set)`.
  `readonly` sigue siendo la primera opción para lo que se fija una vez.
- Trampas: una propiedad hooked no puede ser `readonly`; el hook se evalúa en CADA acceso
  (no meter trabajo caro sin memoizar); el tipo va como `@var`, no `@return`.

Candados: `GoldenRulesPropertyHooksTest` + `check-php-getter-golden-rules.sh`.
