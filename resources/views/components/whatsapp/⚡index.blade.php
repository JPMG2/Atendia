<?php

use Livewire\Component;

/**
 * "WhatsApp" — the panel's home for the number the assistant answers
 * through. The card itself is shared with wizard step 5, so what a new
 * signup sees and what the panel shows can never drift apart.
 */
new class extends Component {};
?>

<div>
    <x-ui.page-head :title="__('whatsapp.title')" :sub="__('whatsapp.sub')" />

    <livewire:whatsapp.link />
</div>
