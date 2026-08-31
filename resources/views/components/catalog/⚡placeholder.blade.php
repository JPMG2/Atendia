<?php

use Livewire\Component;

/**
 * Placeholder editor, rendered while a catalog_forms master has no editor
 * component of its own. Creating `catalog/⚡<entity>.blade.php` makes the hub
 * pick that one up instead.
 */
new class extends Component {};
?>

<div class="catalog-empty">
    <x-icon name="workflow" :size="34" />
    <p>Este maestro todavía no tiene su formulario. Lo armamos en el próximo paso.</p>
</div>
