/*
 * The WhatsApp phone preview: the assistant "types" before each reply, and
 * that one-second wait IS the demo. Shared by the wizard rail and the
 * dashboard simulator so the two canvases can never drift apart.
 */
const state = new WeakMap();

window.wsPhone = {
    render(phone, messages) {
        const s = state.get(phone) ?? { painted: 0, token: 0 };
        state.set(phone, s);

        const bubble = (m) => `<div class="msg ${m.type}"><span class="who">${m.who}</span>${m.html}</div>`;

        // Any repaint invalidates pending animation timers: without this, the
        // bubbles scheduled for the FIRST keystroke fire after a silent
        // repaint and the reply shows up twice.
        if (messages.length === 0) {
            s.token++;
            phone.innerHTML = `<div class="wizard-phone-empty">${phone.dataset.empty}</div>`;
            s.painted = 0;

            return;
        }

        // Same count as already painted means text-only edits: no re-animation.
        if (messages.length === s.painted) {
            s.token++;
            phone.innerHTML = messages.map(bubble).join('');

            return;
        }

        const current = ++s.token;
        s.painted = messages.length;
        phone.innerHTML = '';

        let delay = 0;

        messages.forEach((m) => {
            if (m.type === 'out') {
                const typingAt = delay;
                setTimeout(() => {
                    if (current !== s.token) return;
                    phone.insertAdjacentHTML(
                        'beforeend',
                        '<div class="typing" data-typing><i></i><i></i><i></i></div>',
                    );
                    phone.scrollTop = phone.scrollHeight;
                }, typingAt);
                delay += 900;
            }

            setTimeout(() => {
                if (current !== s.token) return;
                phone.querySelector('[data-typing]')?.remove();
                phone.insertAdjacentHTML('beforeend', bubble(m));
                phone.scrollTop = phone.scrollHeight;
            }, delay);
            delay += 450;
        });
    },
};
