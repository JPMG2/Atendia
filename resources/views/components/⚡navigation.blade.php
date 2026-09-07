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

        {{-- LinkedIn-style profile strength: constant presence, zero pressure.
        Mock at 40%; wired, it derives from the data and vanishes at 100%. --}}
        @if ($panel === 'client')
            <a href="{{ route('dashboard') }}" wire:navigate class="sidebar-progress">
                <span class="sidebar-progress-head">
                    <span>{{ __('menu.profile_progress', ['percent' => 40]) }}</span>
                    <x-icon name="chevron-right" :size="14" />
                </span>
                <span class="setup-bar"><i style="width: 40%"></i></span>
            </a>
        @endif

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
