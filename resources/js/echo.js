import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// The WebSocket comes in through the SAME origin as the page: nginx proxies
// /app/ and /apps/ to Reverb. Hence the browser's hostname and port as the
// default, not a hardcoded IP — the day there is a domain, this keeps
// working untouched.
const scheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
const forceTLS = scheme === 'https';

// The browser's port: 443 through Traefik, and 8081 when testing the
// container directly.
const port = Number(import.meta.env.VITE_REVERB_PORT || window.location.port || (forceTLS ? 443 : 80));

// Private channel: pusher-js hits /broadcasting/auth with its own XHR, which
// goes through neither Livewire nor axios. Without the CSRF token by hand
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
