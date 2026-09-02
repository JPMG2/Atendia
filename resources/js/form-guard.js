/*
 * validate — the front's validation entry point, so a doomed request is never
 * sent. EACH component decides in its own @script what to check.
 *
 *   this.errors = validate(
 *     { code: this.$wire.code, name: this.$wire.name },
 *     { code: ['required', 'alpha', ['length', 3]], name: ['required'] }
 *   );
 *   if (Object.keys(this.errors).length === 0) this.$wire.save();
 *
 * A rule is a string ('required', 'email', 'noMarkup'…) or an array of
 * [name, ...params]. Alpine is NOT imported here: Livewire brings it.
 */

/*
 * The patterns mirror the server's EXACTLY, or the front lets through what the
 * server rejects and the person eats a bounce nobody can explain:
 *
 *   alpha       -> Laravel's `alpha`: letters and marks only, no spaces.
 *   alphaSpaces -> AttributeValidator::ALPHA_PATTERN.
 *   noMarkup    -> AttributeValidator::XSS_PREVENTION_PATTERN.
 */
const RE = {
    email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
    alpha: /^[\p{L}\p{M}]+$/u,
    alphaSpaces: /^[\p{L}\p{M}\s'-]+$/u,
    alphanum: /^[\p{L}\p{N}\s'’-]+$/u,
    noMarkup: /^[^<>]*$/,
};

// Each check returns `true` when it passes, or the error message when it fails.
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
    // Mirrors Laravel's `confirmed`: the caller passes the OTHER value when
    // assembling the rules, e.g. [['same', values.password]].
    same: (v, other) => v === String(other ?? '') || 'Los valores no coinciden.',
};

/**
 * Checks `values` against `rules` and returns { field: firstMessage }. Empty
 * means everything passed.
 */
function validate(values, rules) {
    const errors = {};

    for (const field in rules) {
        const value = values[field] == null ? '' : String(values[field]).trim();

        for (const spec of rules[field]) {
            const [name, ...params] = Array.isArray(spec) ? spec : [spec];

            // Rules other than 'required' are not run against an empty field.
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
