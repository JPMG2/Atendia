/*
 * catalogRail — the search box of the catalog hub's master rail.
 *
 * The rail grew with the number of masters. It now has a fixed height with its
 * own scroll and a search box filtering CLIENT-SIDE, with no request.
 *
 *   catalogRail({ titles: ['Countries', 'Currencies', ...] })  // empty state
 *
 * It registers on the Alpine that Livewire brings, NEVER by importing Alpine
 * here: a second Alpine would break the dashboard.
 */

/**
 * Normalised for comparison: lowercase and WITHOUT accents, so a query typed
 * flat still finds an accented title. Same criterion as the combobox.
 */
function fold(value) {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '');
}

export function catalogRail({ titles = [] } = {}) {
    return {
        q: '',

        /** Does this master match what is being searched for? */
        matches(title) {
            const needle = fold(this.q).trim();

            return needle === '' || fold(title).includes(needle);
        },

        /**
         * A group only shows while it has at least one visible master, or its
         * heading would sit there with nothing underneath.
         */
        groupVisible(groupTitles) {
            return groupTitles.some((title) => this.matches(title));
        },

        hasResults() {
            return titles.some((title) => this.matches(title));
        },

        /**
         * Opening a master collapses the rail to icons and hides the search box.
         * A filter left on would show two or three loose icons with nothing on
         * screen explaining where the rest went.
         */
        clearSearch() {
            this.q = '';
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('catalogRail', catalogRail);
});
