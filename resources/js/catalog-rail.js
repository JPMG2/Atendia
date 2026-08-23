/*
 * catalogRail — el buscador del riel de maestros del hub de catálogos.
 *
 * El riel crecía con la cantidad de maestros: cada catálogo nuevo estiraba la
 * pantalla entera y encontrar uno era bajar con la vista. Ahora el riel tiene
 * alto fijo con scroll propio (`.catalog-rail-body`) y arriba un buscador que
 * filtra CLIENT-SIDE, sin request: los maestros ya vienen renderizados.
 *
 *   catalogRail({ titles: ['Países', 'Monedas', ...] })   // para el "sin resultados"
 *
 * Se registra sobre el Alpine que trae Livewire (`alpine:init`), NUNCA importando
 * Alpine acá: importarlo arrancaría un segundo Alpine y rompería el dashboard.
 */

/**
 * Normaliza para comparar: minúsculas y SIN acentos, así "facturacion" encuentra
 * "Facturación" y "paises" encuentra "Países". Mismo criterio que el combobox.
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

        /** ¿Este maestro entra en lo que se está buscando? */
        matches(title) {
            const needle = fold(this.q).trim();

            return needle === '' || fold(title).includes(needle);
        },

        /**
         * Un grupo se muestra solo si le queda al menos un maestro visible: si no,
         * el encabezado "Ubicaciones" quedaría solo, sin nada debajo.
         */
        groupVisible(groupTitles) {
            return groupTitles.some((title) => this.matches(title));
        },

        hasResults() {
            return titles.some((title) => this.matches(title));
        },

        /**
         * Al abrir un maestro el riel se colapsa a iconos y el buscador se
         * esconde. Si el filtro quedara puesto se verían dos o tres iconos sueltos
         * sin ninguna caja a la vista que explique por qué faltan los demás.
         */
        clearSearch() {
            this.q = '';
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('catalogRail', catalogRail);
});
