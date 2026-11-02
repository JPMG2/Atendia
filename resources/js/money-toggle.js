// The USD | Bs toggle remembers what each person last picked on each screen.
// Dollars stay the default for anyone who never touched it, and blocked storage
// only means the choice is not remembered.
function moneyToggle(key) {
    const storeKey = `atendia-money:${key}`;

    return {
        init() {
            try {
                if (localStorage.getItem(storeKey) === 'ves') {
                    this.ves = true;
                }
            } catch {
                // Storage can be blocked: the toggle still works for this visit.
            }
        },

        pick(showBolivares) {
            this.ves = showBolivares;

            try {
                localStorage.setItem(storeKey, showBolivares ? 'ves' : 'usd');
            } catch {
                // Same as above: nothing to remember with.
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('moneyToggle', moneyToggle);
});
