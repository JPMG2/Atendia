/*
 * The landing hero comes alive after the CSS entrance: the headline types
 * itself, the phone's status bar shows the visitor's real time, and the demo
 * chat keeps breathing with new exchanges. Reduced-motion visitors get the
 * page at rest (the live clock stays — it is information, not motion).
 */
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

// Raised by the interactive demo: the scripted pool yields to the visitor.
let demoActive = false;

const nowTime = () =>
    new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: false });

function startClock() {
    const clock = document.querySelector('[data-hero-clock]');

    if (!clock) return;

    const paint = () => (clock.textContent = nowTime());

    paint();
    setInterval(paint, 30000);
}

function typeHeadline() {
    const el = document.querySelector('[data-hero-type]');

    if (!el || reduced) return;

    const text = el.textContent;

    // Screen readers keep the full promise while the letters land.
    el.setAttribute('aria-label', text);
    el.textContent = '';
    el.classList.add('is-typing');

    let i = 0;

    const step = () => {
        el.textContent = text.slice(0, ++i);

        if (i < text.length) {
            setTimeout(step, 55);
        } else {
            setTimeout(() => el.classList.remove('is-typing'), 1100);
        }
    };

    // Waits for the hero-enter fade so the cursor never types into a ghost.
    setTimeout(step, 600);
}

function liveChat() {
    const chat = document.querySelector('[data-phone-live]');

    if (!chat || reduced) return;

    let pool;

    try {
        pool = JSON.parse(chat.dataset.livePool || '[]');
    } catch {
        return;
    }

    if (pool.length === 0) return;

    const bubble = (m) => {
        const row = document.createElement('div');
        row.className = `pm-row ${m.side}`;
        row.innerHTML = `<div class="pm-bubble ${m.side} phone-bubble">${m.text}<span class="pm-time">${nowTime()}</span></div>`;

        return row;
    };

    const settle = () => {
        // Old rows scroll away like a real chat; the DOM stays bounded.
        while (chat.children.length > 12) chat.firstElementChild.remove();
        chat.scrollTo({ top: chat.scrollHeight, behavior: 'smooth' });
    };

    let i = 0;

    const tick = () => {
        // The visitor took the phone: the script never talks over them.
        if (demoActive) return;

        // A hidden tab just waits: bubbles landing unseen are wasted charm.
        if (document.hidden) return schedule();

        const m = pool[i % pool.length];
        i++;

        if (m.side === 'out') {
            const typing = document.createElement('div');
            typing.className = 'pm-row out';
            typing.innerHTML = '<div class="pm-bubble out pm-typing"><i></i><i></i><i></i></div>';
            chat.append(typing);
            settle();

            setTimeout(() => {
                typing.remove();

                if (demoActive) return;

                chat.append(bubble(m));
                settle();
                schedule();
            }, 1100);
        } else {
            chat.append(bubble(m));
            settle();
            schedule();
        }
    };

    const schedule = () => setTimeout(tick, 4200);

    // Freezes the conversation's rendered height first, so new bubbles
    // scroll inside the screen instead of stretching the phone.
    setTimeout(() => {
        chat.style.height = `${chat.offsetHeight}px`;
        chat.style.minHeight = '0';
        chat.style.overflow = 'hidden';
        schedule();
    }, 2400);
}

function interactiveDemo() {
    const box = document.querySelector('[data-demo]');
    const chat = document.querySelector('[data-phone-live]');

    if (!box || !chat) return;

    const form = box.querySelector('[data-demo-form]');
    const input = box.querySelector('[data-demo-input]');
    const sendBtn = box.querySelector('[data-demo-send]');
    const cta = box.querySelector('[data-demo-cta]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const settle = () => {
        while (chat.children.length > 12) chat.firstElementChild.remove();
        chat.scrollTo({ top: chat.scrollHeight, behavior: 'smooth' });
    };

    // textContent on purpose: the visitor's words and the model's reply are
    // data, never markup.
    const bubble = (side, text) => {
        const row = document.createElement('div');
        row.className = `pm-row ${side}`;

        const body = document.createElement('div');
        body.className = `pm-bubble ${side} phone-bubble`;
        body.textContent = text;

        const time = document.createElement('span');
        time.className = 'pm-time';
        time.textContent = nowTime();

        body.append(time);
        row.append(body);
        chat.append(row);
        settle();
    };

    const typing = () => {
        const row = document.createElement('div');
        row.className = 'pm-row out';
        row.innerHTML = '<div class="pm-bubble out pm-typing"><i></i><i></i><i></i></div>';
        chat.append(row);
        settle();

        return row;
    };

    let busy = false;
    let finished = false;

    const finish = () => {
        finished = true;
        form.style.display = 'none';
        box.querySelectorAll('[data-demo-chip]').forEach((chip) => chip.remove());
        cta.style.display = 'inline-flex';
    };

    const send = async (text) => {
        const message = text.trim();

        if (message === '' || busy || finished) return;

        busy = true;
        demoActive = true;

        // Reduced-motion visitors skipped the pool's height freeze: freeze
        // now, so the real conversation scrolls instead of stretching.
        if (!chat.style.height) {
            chat.style.height = `${chat.offsetHeight}px`;
            chat.style.minHeight = '0';
            chat.style.overflow = 'hidden';
        }

        input.value = '';
        bubble('in', message);
        const dots = typing();

        try {
            const response = await fetch(box.dataset.endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ message }),
            });
            const data = response.ok ? await response.json() : { error: true };

            dots.remove();

            if (data.reply) bubble('out', data.reply);
            if (data.error) bubble('out', box.dataset.errorReply);

            if (data.done) {
                bubble('out', box.dataset.limitReply);
                finish();
            }
        } catch {
            dots.remove();
            bubble('out', box.dataset.errorReply);
        } finally {
            busy = false;
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        send(input.value);
    });
    sendBtn.addEventListener('click', () => send(input.value));
    box.querySelectorAll('[data-demo-chip]').forEach((chip) =>
        chip.addEventListener('click', () => send(chip.textContent)),
    );
}

function revealSections() {
    if (reduced || !('IntersectionObserver' in window)) return;

    // Every landing section below the hero; hiding happens HERE, not in the
    // markup, so a browser without JS still shows the whole page at rest.
    const sections = document.querySelectorAll('main section:not(#top)');

    if (sections.length === 0) return;

    const io = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.remove('reveal-pending');
                entry.target.classList.add('reveal-in');
                io.unobserve(entry.target);
            });
        },
        { rootMargin: '0px 0px -10% 0px' },
    );

    sections.forEach((section) => {
        section.classList.add('reveal-pending');
        io.observe(section);
    });
}

startClock();
typeHeadline();
liveChat();
interactiveDemo();
revealSections();
