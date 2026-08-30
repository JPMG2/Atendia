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

/**
 * Shortest query that gets highlighted. One letter matches nearly every title
 * and speckles the rail with loose marks; from two on, the mark points
 * somewhere. Filtering still starts at the first keystroke.
 */
const MIN_HIGHLIGHT = 2;

export function catalogRail({ titles = [] } = {}) {
    return {
        q: '',

        /** Does this master match what is being searched for? */
        matches(title) {
            const needle = fold(this.q).trim();

            return needle === '' || fold(title).includes(needle);
        },

        /** Whether the query is long enough to be worth marking. */
        searching() {
            return fold(this.q).trim().length >= MIN_HIGHLIGHT;
        },

        /**
         * The title split into what matches the query and what does not, so the
         * view can mark the hits without ever building HTML out of data.
         *
         * Matched on the FOLDED title and sliced out of the ORIGINAL, so typing
         * without accents still highlights the accented text. The guard bails out
         * on any character where folding would not preserve the length.
         *
         * @returns {{text: string, hit: boolean}[]}
         */
        segments(title) {
            const needle = fold(this.q).trim();
            const haystack = fold(title);

            if (needle.length < MIN_HIGHLIGHT || haystack.length !== title.length) {
                return [{ text: title, hit: false }];
            }

            const parts = [];
            let from = 0;

            for (let at = haystack.indexOf(needle); at !== -1; at = haystack.indexOf(needle, from)) {
                if (at > from) {
                    parts.push({ text: title.slice(from, at), hit: false });
                }

                parts.push({ text: title.slice(at, at + needle.length), hit: true });
                from = at + needle.length;
            }

            if (from < title.length) {
                parts.push({ text: title.slice(from), hit: false });
            }

            return parts;
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
