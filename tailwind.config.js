import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class', // `.dark` en <html> activa el tema oscuro (tokens en app.css)

    // El tag de estado arma su clase en runtime (`'is-' + row.color`, con el color
    // que viene de la base), así que el scanner de Tailwind nunca la ve en el
    // fuente y el purge se llevaría los estilos. Acá se declaran para que existan
    // sí o sí en el build. La paleta espeja CurrentStatus::COLORS.
    safelist: [
        'status-tag',
        'is-success',
        'is-info',
        'is-warning',
        'is-danger',
        'is-brand',
        'is-neutral',
    ],

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                display: ['Sora', ...defaultTheme.fontFamily.sans],
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
        },
    },

    plugins: [forms],
};
