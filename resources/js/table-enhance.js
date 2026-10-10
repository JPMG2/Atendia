/*
 * Admin tables: sortable headings and clickable rows, from the markup alone.
 *
 * A table opts in to sorting with `data-sortable`; a row opts in to being
 * clickable by holding one `[data-row-action]` (the link or button that
 * already does what the row is for). Delegated on the document so it survives
 * wire:navigate and Livewire morphs without re-binding anything.
 */

const collator = new Intl.Collator('es', { numeric: true, sensitivity: 'base' });

/**
 * Runs a row's action. A wire:navigate link ignores a scripted click (Livewire
 * navigates on mousedown/mouseup), so it is handed to Livewire directly.
 */
function runAction(action) {
    if (action.matches('a[wire\\:navigate]') && window.Livewire?.navigate) {
        window.Livewire.navigate(action.href);

        return;
    }

    action.click();
}
const INTERACTIVE = 'a, button, input, select, textarea, label, summary, [role="button"], [data-no-row-go]';

const sortState = new WeakMap(); // table -> { col, dir }
const origin = new WeakMap(); // row -> position the server gave it
let originSeq = 0;

/** A cell's sortable value: an explicit data-sort-value wins, then its text. */
function cellValue(cell) {
    const raw = (cell.dataset.sortValue ?? cell.textContent).trim();

    if (raw === '' || raw === '—' || raw === '-') {
        return { empty: true };
    }

    const date = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?/);
    if (date) {
        return { num: Date.UTC(+date[3], +date[2] - 1, +date[1], +(date[4] ?? 0), +(date[5] ?? 0)) };
    }

    // Figures are written es-style (1.234,56): only a cell that is mostly a figure counts as one.
    const figure = raw.match(/-?\d[\d.]*(?:,\d+)?/);
    if (figure && raw.replace(figure[0], '').trim().length <= 5) {
        const num = parseFloat(figure[0].replace(/\./g, '').replace(',', '.'));

        if (!Number.isNaN(num)) {
            return { num };
        }
    }

    return { text: raw };
}

function compare(a, b, dir) {
    // A missing value goes last either way: sorting must not lead with blanks.
    if (a.empty || b.empty) {
        return a.empty === b.empty ? 0 : (a.empty ? 1 : -1);
    }

    const bothNum = a.num !== undefined && b.num !== undefined;
    const result = bothNum ? a.num - b.num : collator.compare(a.text ?? String(a.num), b.text ?? String(b.num));

    return dir === 'asc' ? result : -result;
}

function headings(table) {
    return Array.from(table.querySelectorAll(':scope > thead > tr:last-child > th'));
}

/** Re-marks the headings: a morph rewrites attributes the server never sent. */
function markHeadings(table) {
    const state = sortState.get(table);

    headings(table).forEach((th, index) => {
        if (th.textContent.trim() === '') {
            return;
        }

        th.setAttribute('tabindex', '0');
        th.dataset.sortable = '';

        const aria = state && state.col === index ? (state.dir === 'asc' ? 'ascending' : 'descending') : null;

        if (aria) {
            th.setAttribute('aria-sort', aria);
        } else {
            th.removeAttribute('aria-sort');
        }
    });
}

function applySort(table) {
    const body = table.tBodies[0];

    if (!body) {
        return;
    }

    restoreSort(table);

    const rows = Array.from(body.rows);

    rows.forEach((row) => {
        if (!origin.has(row)) {
            origin.set(row, originSeq++);
        }
    });

    const state = sortState.get(table);
    const sorted = rows.slice();

    if (state) {
        const values = new Map(rows.map((row) => [row, row.cells[state.col] ? cellValue(row.cells[state.col]) : { empty: true }]));
        sorted.sort((x, y) => compare(values.get(x), values.get(y), state.dir) || origin.get(x) - origin.get(y));
    } else {
        sorted.sort((x, y) => origin.get(x) - origin.get(y));
    }

    // Only touch the DOM when the order really differs, so the observer never feeds on itself.
    if (sorted.some((row, i) => row !== rows[i])) {
        body.append(...sorted);
    }

    markHeadings(table);
}

const STORE = 'atendia-table-sort';
const restored = new WeakSet();

/** One key per table: the screen plus the table's place among the sortable ones. */
function storeKey(table) {
    const index = Array.from(document.querySelectorAll('table[data-sortable]')).indexOf(table);

    return `${location.pathname}#${index}`;
}

function readStore() {
    try {
        return JSON.parse(localStorage.getItem(STORE) ?? '{}') ?? {};
    } catch {
        return {};
    }
}

function writeStore(table) {
    try {
        const all = readStore();
        const state = sortState.get(table);

        if (state) {
            all[storeKey(table)] = state;
        } else {
            delete all[storeKey(table)];
        }

        localStorage.setItem(STORE, JSON.stringify(all));
    } catch {
        // Storage can be blocked: the sort still works, it just is not remembered.
    }
}

/** A table seen for the first time takes the order she last chose on it. */
function restoreSort(table) {
    if (restored.has(table)) {
        return;
    }

    restored.add(table);

    const saved = readStore()[storeKey(table)];
    const th = saved ? headings(table)[saved.col] : null;

    if (th && th.textContent.trim() !== '' && (saved.dir === 'asc' || saved.dir === 'desc')) {
        sortState.set(table, { col: saved.col, dir: saved.dir });
    }
}

function toggleSort(th) {
    const table = th.closest('table[data-sortable]');

    if (!table || th.textContent.trim() === '') {
        return;
    }

    const col = headings(table).indexOf(th);
    const current = sortState.get(table);

    // Seen order first, then the reverse, then back to what the server gave.
    if (!current || current.col !== col) {
        sortState.set(table, { col, dir: 'asc' });
    } else if (current.dir === 'asc') {
        sortState.set(table, { col, dir: 'desc' });
    } else {
        sortState.delete(table);
    }

    writeStore(table);
    applySort(table);
}

document.addEventListener('click', (event) => {
    const th = event.target.closest('table[data-sortable] > thead th');

    if (th) {
        toggleSort(th);

        return;
    }

    const row = event.target.closest('.pay-table > tbody > tr');

    if (!row || event.target.closest(INTERACTIVE) || window.getSelection()?.toString()) {
        return;
    }

    const action = row.querySelector('[data-row-action]');

    if (action) {
        runAction(action);
    }
});

document.addEventListener('keydown', (event) => {
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches?.('table[data-sortable] > thead th')) {
        event.preventDefault();
        toggleSort(event.target);
    }
});

/* Keyboard: j / k walk the rows of the table in view, Enter runs the row's action. */
let cursor = null;

function visibleRows(table) {
    return Array.from(table.tBodies[0]?.rows ?? []).filter((row) => row.offsetParent !== null);
}

function tableInView() {
    if (cursor?.isConnected) {
        return cursor.closest('table');
    }

    return Array.from(document.querySelectorAll('.pay-table[data-sortable]')).find((table) => {
        const box = table.getBoundingClientRect();

        return table.offsetParent !== null && box.bottom > 0 && box.top < window.innerHeight;
    });
}

function moveCursor(step) {
    const table = tableInView();
    const rows = table ? visibleRows(table) : [];

    if (rows.length === 0) {
        return;
    }

    const at = cursor ? rows.indexOf(cursor) : -1;
    const next = rows[Math.min(rows.length - 1, Math.max(0, at === -1 ? (step > 0 ? 0 : rows.length - 1) : at + step))];

    cursor?.removeAttribute('data-kb');
    cursor = next;
    cursor.setAttribute('data-kb', '');
    cursor.scrollIntoView({ block: 'nearest' });
}

document.addEventListener('keydown', (event) => {
    const typing = event.target.closest?.('input, select, textarea, [contenteditable], [role="combobox"], [role="dialog"], dialog');

    if (typing || event.metaKey || event.ctrlKey || event.altKey) {
        return;
    }

    // A screen binds a key from its markup: `data-key-focus` on a field takes the
    // focus to it (a combobox forwards its attributes to the hidden value, so the
    // visible input is looked up in the field's own box), `data-key-click` clicks a control.
    const focusBox = document.querySelector(`[data-key-focus="${event.key}"]`);
    const clickBox = document.querySelector(`[data-key-click="${event.key}"]`);

    if (event.key === '?') {
        const help = keyHelp();

        if (help) {
            event.preventDefault();
            window.dialog.notify({ type: 'info', title: help.title, message: help.message });
        }
    } else if (focusBox) {
        event.preventDefault();
        (focusBox.closest('.field')?.querySelector('input[role="combobox"]') ?? focusBox).focus();
    } else if (clickBox) {
        event.preventDefault();
        clickBox.click();
    } else if (event.key === 'j' || event.key === 'k') {
        event.preventDefault();
        moveCursor(event.key === 'j' ? 1 : -1);
    } else if (event.key === 'Enter' && cursor?.isConnected && event.target === document.body) {
        const action = cursor.querySelector('[data-row-action]') ?? (cursor.hasAttribute('wire:click') ? cursor : null);

        if (action) {
            runAction(action);
        }
    } else if (event.key === 'Escape' && cursor) {
        cursor.removeAttribute('data-kb');
        cursor = null;
    }
});

/**
 * The screen's own key line, read back as a list: the hint line stays the one
 * place a key is written down, so the dialog can never disagree with it.
 */
function keyHelp() {
    const hints = document.querySelector('.key-hints');

    if (!hints) {
        return null;
    }

    const lines = [];
    let keys = [];

    hints.childNodes.forEach((node) => {
        if (node.nodeName === 'KBD') {
            keys.push(node.textContent.trim());

            return;
        }

        const text = node.textContent.trim();

        if (text !== '' && keys.length > 0) {
            lines.push(`${keys.join('  ')}   ${text}`);
            keys = [];
        }
    });

    return { title: hints.dataset.helpTitle ?? '', message: lines.join('\n') };
}

let pending = false;

function refreshAll() {
    pending = false;
    document.querySelectorAll('table[data-sortable]').forEach(applySort);

    // A morph rewrites attributes the server never sent: the cursor is put back.
    if (cursor?.isConnected && !cursor.hasAttribute('data-kb')) {
        cursor.setAttribute('data-kb', '');
    }
}

new MutationObserver(() => {
    if (!pending) {
        pending = true;
        requestAnimationFrame(refreshAll);
    }
}).observe(document.documentElement, { childList: true, subtree: true });

document.addEventListener('livewire:navigated', refreshAll);
refreshAll();
