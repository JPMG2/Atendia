<?php

use Livewire\Component;

/**
 * Editor placeholder: se renderiza cuando un maestro de catalog_forms todavía
 * no tiene su componente editor propio. Al crear `catalog/⚡<entity>.blade.php`,
 * el hub lo usa automáticamente en lugar de este.
 */
new class extends Component {};
?>

<div class="catalog-empty">
    <x-icon name="workflow" :size="34" />
    <p>Este maestro todavía no tiene su formulario. Lo armamos en el próximo paso.</p>
</div>
