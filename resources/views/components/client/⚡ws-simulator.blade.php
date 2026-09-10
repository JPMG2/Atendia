<?php

use App\Classes\Main\AssistantPreview;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The live WhatsApp simulator beside the offer screens: the assistant
 * answering a client with the tenant's REAL loaded data — cause and effect,
 * never a canned demo. It speaks through the same builder and copy as the
 * wizard rail, so the product keeps one voice.
 */
new class extends Component
{
    public string $businessName = '';

    /**
     * Locked: the conversation is composed server-side from the tenant's own
     * rows and painted as raw HTML — the client must not be able to type it.
     *
     * @var list<array{type: string, who: string, html: string}>
     */
    #[Locked]
    public array $messages = [];

    public function mount(): void
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return;
        }

        $this->businessName = $business->name;
        $this->messages = AssistantPreview::messages(
            $business->name,
            $business->serviceNames(),
            $business->productNames(),
        );
    }
};
?>

<aside class="ws-simulator">
    <span class="ws-simulator-tag">{{ __('client.simulator.tag') }}</span>
    <p class="ws-simulator-sub">{{ __('client.simulator.sub') }}</p>
    <x-client.ws-phone
        :name="$businessName !== '' ? $businessName : __('wizard.preview.header')"
        :empty="__('client.simulator.empty')"
    />
</aside>

@script
    <script>
        // Painted once per visit: the typing pause IS the demo.
        wsPhone.render($wire.$el.querySelector('[data-phone]'), $wire.messages);
    </script>
@endscript
