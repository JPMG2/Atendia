/*
 * validate — función madre de validación en el front (evita requests innecesarios).
 *
 * Global y reutilizable. CADA componente decide, en su propio @script, qué valores
 * validar (leídos de $wire) y con qué reglas. Uso:
 *
 *   this.errors = validate(
 *     { code: this.$wire.code, name: this.$wire.name },
 *     { code: ['required', 'alpha', ['length', 3]], name: ['required', ['minLength', 3]] }
 *   );
 *   if (Object.keys(this.errors).length === 0) this.$wire.save();
 *
 * Cada regla es un string ('required', 'email', 'numeric', 'integer', 'date',
 * 'alpha', 'alphaSpaces', 'alphanum', 'noMarkup') o un array [nombre, ...params]
 * (['minLength', 3], ['maxLength', 10], ['length', 3], ['min', 0], ['max', 8],
 * ['between', 0, 8]).
 *
 * NO se importa Alpine acá: en el dashboard el Alpine lo trae Livewire.
 */

/*
 * Los patrones espejan EXACTAMENTE los del server, o el front deja pasar algo que
 * el server rechaza (o al revés) y el usuario come un rebote que no se explica:
 *
 *   alpha       -> regla `alpha` de Laravel: SOLO letras y marcas, sin espacios.
 *   alphaSpaces -> AttributeValidator::ALPHA_PATTERN (letras + espacios ' -).
 *   noMarkup    -> AttributeValidator::XSS_PREVENTION_PATTERN (sin < ni >).
 */
const RE = {
    email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    alpha: /^[\p{L}\p{M}]+$/u,
    alphaSpaces: /^[\p{L}\p{M}\s'-]+$/u,
    alphanum: /^[\p{L}\p{N}\s'’-]+$/u,
    noMarkup: /^[^<>]*$/,
};

// Cada check devuelve `true` si pasa, o el mensaje de error (string) si falla.
const CHECKS = {
    required: (v) => v !== '' || 'Este campo es obligatorio.',
    email: (v) => RE.email.test(v) || 'Ingresá un email válido.',
    numeric: (v) => (isFinite(Number(v)) && v !== '') || 'Debe ser un número.',
    integer: (v) => Number.isInteger(Number(v)) || 'Debe ser un número entero.',
    date: (v) => !isNaN(Date.parse(v)) || 'Ingresá una fecha válida.',
    alpha: (v) => RE.alpha.test(v) || 'Solo se admiten letras.',
    alphaSpaces: (v) => RE.alphaSpaces.test(v) || 'Solo se admiten letras y espacios.',
    alphanum: (v) => RE.alphanum.test(v) || 'Solo se admiten letras y números.',
    noMarkup: (v) => RE.noMarkup.test(v) || 'No se admiten los caracteres < ni >.',
    minLength: (v, n) => v.length >= n || `Debe tener al menos ${n} caracteres.`,
    maxLength: (v, n) => v.length <= n || `No puede superar ${n} caracteres.`,
    length: (v, n) => v.length === n || `Debe tener exactamente ${n} caracteres.`,
    min: (v, n) => Number(v) >= n || `Debe ser mayor o igual a ${n}.`,
    max: (v, n) => Number(v) <= n || `Debe ser menor o igual a ${n}.`,
    between: (v, a, b) => (Number(v) >= a && Number(v) <= b) || `Debe estar entre ${a} y ${b}.`,
};

/**
 * Valida `values` contra `rules` y devuelve { campo: primerMensaje }. Vacío = todo ok.
 */
function validate(values, rules) {
    const errors = {};

    for (const field in rules) {
        const value = values[field] == null ? '' : String(values[field]).trim();

        for (const spec of rules[field]) {
            const [name, ...params] = Array.isArray(spec) ? spec : [spec];

            // Las reglas que no son 'required' no se evalúan sobre un campo vacío (opcional).
            if (name !== 'required' && value === '') {
                continue;
            }

            const check = CHECKS[name];
            if (!check) {
                continue; // regla desconocida → se ignora
            }

            const result = check(value, ...params);
            if (result !== true) {
                errors[field] = result; // primer error del campo, y corta
                break;
            }
        }
    }

    return errors;
}

window.validate = validate;
