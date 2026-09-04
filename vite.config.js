import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/form-guard.js', 'resources/js/dialog.js', 'resources/js/combobox.js', 'resources/js/file-field.js', 'resources/js/phone-field.js', 'resources/js/catalog-master.js', 'resources/js/catalog-rail.js', 'resources/js/echo.js'],
            refresh: true,
        }),
    ],
});
