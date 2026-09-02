<?php

use App\Models\Menu;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Active panel (admin | client). Fixed in mount from the route so it
     * survives Livewire updates instead of depending on the live request.
     */
    public string $panel = 'client';

    public function mount(): void
    {
        $this->panel = request()->routeIs('admin.*') ? 'admin' : 'client';
    }

    /**
     * Menu tree of the active panel, memoized per request.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Menu>
     */
    #[Computed]
    public function tree()
    {
        return Menu::tree($this->panel);
    }
};
?>

<nav class="sidebar-nav" aria-label="{{ __('menu.aria_nav') }}">
    <p class="menu-section">{{ __('menu.section') }}</p>

    <x-ui.menu :items="$this->tree->where('placement', 'main')->values()" />

    <div class="sidebar-nav-bottom">
        <x-ui.menu :items="$this->tree->where('placement', 'bottom')->values()" />

        {{-- The plan upsell talks to a CLIENT on a trial; the admin panel is
        the owner's and has no plan to improve. --}}
        @if ($panel === 'client')
            <div class="sidebar-upsell">
                <div class="sidebar-upsell-head">
                    <x-icon name="zap" :size="16" />
                    <span>{{ __('menu.plan_name') }}</span>
                </div>
                <p class="sidebar-upsell-text">{{ __('menu.plan_trial') }}</p>
                <x-ui.button variant="primary" size="sm" :fullWidth="true">{{ __('menu.plan_cta') }}</x-ui.button>
            </div>
        @endif
    </div>
</nav>
