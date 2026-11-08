// Google Analytics loads only after the visitor says yes. The choice lives in
// localStorage; blocked storage just means the banner asks again next visit.
const KEY = 'atendia-cookies';

const root = document.querySelector('[data-consent]');

function readChoice() {
    try {
        return localStorage.getItem(KEY);
    } catch (e) {
        return null;
    }
}

function saveChoice(choice) {
    try {
        localStorage.setItem(KEY, choice);
    } catch (e) {}
}

let loaded = false;

function load(id) {
    if (loaded) return;
    loaded = true;

    window.dataLayer = window.dataLayer || [];
    window.gtag = function () {
        window.dataLayer.push(arguments);
    };
    window.gtag('js', new Date());
    // No ad personalisation: the page is measured, the visitor is not profiled.
    window.gtag('config', id, { allow_google_signals: false, allow_ad_personalization_signals: false });

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(id)}`;
    document.head.appendChild(script);
}

// Saying no after a yes must also take the cookies Google already left.
function forget(id) {
    window[`ga-disable-${id}`] = true;

    const host = location.hostname.split('.');
    const domains = ['', location.hostname];

    for (let i = 1; i < host.length - 1; i += 1) {
        domains.push(`.${host.slice(i).join('.')}`);
    }

    document.cookie.split(';').forEach((raw) => {
        const name = raw.split('=')[0].trim();

        if (name === '_ga' || name.startsWith('_ga_')) {
            domains.forEach((domain) => {
                document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/${domain ? `; domain=${domain}` : ''}`;
            });
        }
    });
}

if (root) {
    const id = root.dataset.consent;
    const show = (visible) => root.toggleAttribute('hidden', !visible);

    document.querySelector('[data-consent-accept]')?.addEventListener('click', () => {
        saveChoice('granted');
        show(false);
        load(id);
    });

    document.querySelector('[data-consent-reject]')?.addEventListener('click', () => {
        saveChoice('denied');
        show(false);
        forget(id);
    });

    // The footer link brings the banner back so the choice can be changed.
    document.querySelectorAll('[data-consent-open]').forEach((button) => {
        button.addEventListener('click', () => show(true));
    });

    const choice = readChoice();

    if (choice === 'granted') {
        load(id);
    } else if (choice !== 'denied') {
        show(true);
    }
}
