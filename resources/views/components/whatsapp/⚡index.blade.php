<?php

use Livewire\Component;

/**
 * "WhatsApp" — the panel's home for the number the assistant answers
 * through. The card itself is shared with wizard step 5, so what a new
 * signup sees and what the panel shows can never drift apart. While the
 * business is paused for a lost phone there is no QR to offer: only the way back.
 */
new class extends Component {};
?>

<div class="space-y-4">
    <x-ui.page-head :title="__('whatsapp.title')" :sub="__('whatsapp.sub')" />

    @unless (auth()->user()?->business?->isPausedForLostPhone())
        <livewire:whatsapp.link />
    @endunless

    <livewire:whatsapp.lost-phone />
</div>
