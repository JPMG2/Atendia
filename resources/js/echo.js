import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// El WebSocket entra por el MISMO origen que la pagina: nginx proxea /app/ y
// /apps/ a Reverb (ver docker/nginx/conf.d/laravel.conf). Por eso el default es
// location.hostname y el puerto del navegador, no la IP ni el 8080 cableados:
// el dia que haya dominio, esto sigue andando sin tocar nada.
const scheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
const forceTLS = scheme === 'https';

// El puerto del navegador: 443 cuando entra por Traefik (https, sin puerto en la
// URL) y 8081 cuando se prueba el acceso directo al contenedor.
const port = Number(import.meta.env.VITE_REVERB_PORT || window.location.port || (forceTLS ? 443 : 80));

// Canal privado (business.{id}): pusher-js pega en /broadcasting/auth con su
// propio XHR, que no pasa por Livewire ni por axios. Sin el X-CSRF-TOKEN a mano
// Laravel responde 419 y no se entra a ningun canal.
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': csrfToken,
        },
    },
});
