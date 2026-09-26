/*
 * The landing hero comes alive after the CSS entrance: the headline types
 * itself, the phone's status bar shows the visitor's real time, and the demo
 * chat keeps breathing with new exchanges. Reduced-motion visitors get the
 * page at rest (the live clock stays — it is information, not motion).
 */
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

// Raised by the interactive demo: the scripted pool yields to the visitor.
let demoActive = false;

// Wires the carousel, the scripted player and the composer together: the
// active tag, its business and its conversation always travel as one.
const demoSync = { playPool: null, applyRubro: null };

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

    let pools;

    try {
        pools = JSON.parse(chat.dataset.livePools || '{}');
    } catch {
        return;
    }

    // The first rubro tag opens the show: commerce leads, where a
    // bookings-only rival cannot follow.
    let pool = pools[document.querySelector('[data-demo-rubro]')?.dataset.demoRubro] ?? [];
    let i = 0;
    let timer = null;

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

    const tick = () => {
        // The visitor took the phone: the script never talks over them.
        if (demoActive || pool.length === 0) return;

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

            // Tracked in the same timer so a rubro switch can cancel it: an
            // old pool's reply must never land in the new business's chat.
            timer = setTimeout(() => {
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

    const schedule = (delay = 4200) => (timer = setTimeout(tick, delay));

    // The carousel handed the phone to another rubro: its script starts over.
    demoSync.playPool = (slug) => {
        clearTimeout(timer);
        pool = pools[slug] ?? [];
        i = 0;

        if (pool.length > 0) schedule(1300);
    };

    if (pool.length === 0) return;

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
    const left = box.querySelector('[data-demo-left]');
    const share = box.querySelector('[data-demo-share]');
    const header = document.querySelector('[data-demo-header]');
    const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let rubro = document.querySelector('[data-demo-rubro]')?.dataset.demoRubro ?? 'clinica';
    let currentName = document.querySelector('[data-demo-rubro]')?.dataset.demoName ?? '';
    let currentCtaLabel = document.querySelector('[data-demo-rubro]')?.dataset.demoCtaLabel ?? '';
    const exchanges = [];

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
        left.style.display = 'none';
        box.querySelectorAll('[data-demo-chip]').forEach((chip) => chip.remove());

        // The invite names the visitor's own rubro: "mi peluquería" sells
        // harder than a generic assistant.
        if (currentCtaLabel) cta.textContent = currentCtaLabel;
        cta.style.display = 'inline-flex';

        // The chat itself becomes the pitch, shared on WhatsApp of course.
        if (exchanges.length > 0) {
            const chatText = exchanges.map((e) => `» ${e.q}\n🤖 ${e.a}`).join('\n\n');
            const text = box.dataset.shareTemplate
                .replace('__NAME__', currentName)
                .replace('__CHAT__', chatText);

            share.href = 'https://wa.me/?text=' + encodeURIComponent(text);
            share.style.display = 'inline-flex';
        }
    };

    const paintLeft = (count) => {
        if (typeof count !== 'number' || count < 1) return;

        left.textContent =
            count === 1 ? left.dataset.leftOne : left.dataset.leftMany.replace('__N__', count);
        left.style.display = 'block';
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
                body: JSON.stringify({ message, rubro }),
            });
            const data = response.ok ? await response.json() : { error: true };

            dots.remove();

            if (data.reply) {
                bubble('out', data.reply);
                exchanges.push({ q: message, a: data.reply });
            }
            if (data.error) bubble('out', box.dataset.errorReply);

            if (data.done) {
                bubble('out', box.dataset.limitReply);
                finish();
            } else {
                paintLeft(data.left);
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

    // Handing the phone to a rubro is one move: header, live dot, chips and
    // the composer's target switch together, whoever asked for it.
    const applyRubro = (pill) => {
        rubro = pill.dataset.demoRubro;
        currentName = pill.dataset.demoName;
        currentCtaLabel = pill.dataset.demoCtaLabel ?? '';

        if (header) header.textContent = pill.dataset.demoHeaderText;

        document.querySelectorAll('[data-demo-rubro]').forEach((other) => {
            const active = other === pill;
            other.setAttribute('aria-pressed', active ? 'true' : 'false');
            other.classList.toggle('bg-brand-soft', active);
            other.classList.toggle('text-brand', active);
            other.classList.toggle('bg-sunken', !active);
            other.classList.toggle('text-body', !active);
            other.classList.toggle('hover:bg-brand-soft', !active);
        });

        box.querySelectorAll('[data-demo-chips]').forEach((group) => {
            group.style.display = group.dataset.demoChips === rubro ? 'flex' : 'none';
        });

        // A fresh chat never sits empty: the new assistant says hello.
        chat.innerHTML = '';
        bubble('out', pill.dataset.demoGreeting);
    };

    // The carousel's scripted switch: same plumbing, no takeover.
    demoSync.applyRubro = (pill) => {
        if (demoActive || busy) return;

        applyRubro(pill);
    };

    // Picking a rubro hands the phone to THAT demo business for real.
    document.querySelectorAll('[data-demo-rubro]').forEach((pill) =>
        pill.addEventListener('click', () => {
            if (busy || pill.dataset.demoRubro === rubro) return;

            demoActive = true;
            applyRubro(pill);
        }),
    );
}

function rubroCarousel() {
    const track = document.querySelector('[data-demo-rubro-track]');
    const pills = track ? [...track.querySelectorAll('[data-demo-rubro]')] : [];

    if (pills.length === 0) return;

    let idx = 0;
    let stopped = false;

    // The visitor took the selector: their rubro must never rotate away
    // mid-chat, so any interaction parks the carousel for good.
    const stop = () => (stopped = true);

    pills.forEach((pill) => pill.addEventListener('click', stop));
    document.querySelector('[data-demo-input]')?.addEventListener('focus', stop);
    document.querySelectorAll('[data-demo-chip]').forEach((chip) => chip.addEventListener('click', stop));

    // The active pill leads the visible window of three.
    const paint = () =>
        pills.forEach((pill, i) => {
            pill.style.display = (i - idx + pills.length) % pills.length < 3 ? '' : 'none';
        });

    // A campaign door: ?rubro=veterinaria lands with that demo already
    // active, its pill in view and the carousel parked.
    const wanted = new URLSearchParams(location.search).get('rubro');
    const target = pills.find((pill) => pill.dataset.demoRubro === wanted);

    if (target) {
        idx = pills.indexOf(target);
        paint();
        target.click();
    }

    if (stopped || pills.length <= 3 || reduced) return;

    const tick = () => {
        if (stopped || demoActive) return;
        if (document.hidden) return schedule();

        track.style.opacity = '0';

        setTimeout(() => {
            // A click may land during the fade: leave the demo alone then.
            if (!stopped && !demoActive) {
                idx = (idx + 1) % pills.length;
                paint();

                // The whole demo travels with the tag: live dot, header,
                // chips and a scripted chat that belongs to THIS business.
                demoSync.applyRubro?.(pills[idx]);
                demoSync.playPool?.(pills[idx].dataset.demoRubro);
            }

            track.style.opacity = '1';
        }, 250);

        schedule();
    };

    // Long enough for a rubro's greeting and both scripted exchanges to
    // play out before the next business takes the phone.
    const schedule = () => setTimeout(tick, 14000);

    schedule();
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
rubroCarousel();
revealSections();
