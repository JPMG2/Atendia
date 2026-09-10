<?php

use App\Classes\Main\Client;
use App\Models\Menu;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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
     * @return Collection<int, Menu>
     */
    #[Computed]
    public function tree()
    {
        return Menu::tree($this->panel);
    }

    /**
     * The real profile strength, from the one class that sees every piece.
     *
     * @return array{done: int, total: int, missing: list<string>}
     */
    #[Computed]
    public function strength(): array
    {
        return Client::for(Auth::user())->profileStrength();
    }

    #[Computed]
    public function profilePercent(): int
    {
        return (int) round($this->strength['done'] / max(1, $this->strength['total']) * 100);
    }
};
?>

<nav class="sidebar-nav" aria-label="{{ __('menu.aria_nav') }}">
    <p class="menu-section">{{ __('menu.section') }}</p>

    <x-ui.menu :items="$this->tree->where('placement', 'main')->values()" />

    <div class="sidebar-nav-bottom">
        <x-ui.menu :items="$this->tree->where('placement', 'bottom')->values()" />

        {{-- LinkedIn-style profile strength: constant presence, zero pressure.
        Derived from the real data; at 100% it vanishes — job done. --}}
        @if ($panel === 'client' && auth()->check() && $this->profilePercent < 100)
            <a href="{{ route('my-business') }}" wire:navigate class="sidebar-progress">
                <span class="sidebar-progress-head">
                    <span>{{ __('menu.profile_progress', ['percent' => $this->profilePercent]) }}</span>
                    <x-icon name="chevron-right" :size="14" />
                </span>
                <span class="setup-bar"><i style="width: {{ $this->profilePercent }}%"></i></span>
                {{-- Below 100% the missing list is never empty; naming the
                next piece turns the meter into a to-do, not a grade. --}}
                <span class="sidebar-progress-hint">{{ __('menu.profile_missing.'.$this->strength['missing'][0]) }}</span>
            </a>
        @elseif ($panel === 'client' && auth()->check())
            {{-- At 100% the meter bows out with one goodbye instead of
            vanishing in silence; the dismissal sticks per browser. --}}
            <div
                class="sidebar-done"
                x-data="{ shown: localStorage.getItem('atendia-profile-done') !== '1' }"
                x-show="shown"
                x-cloak
            >
                <x-icon name="circle-check" :size="16" />
                <p>{{ __('menu.profile_done') }}</p>
                <button
                    type="button"
                    aria-label="{{ __('menu.profile_done_close') }}"
                    @click="shown = false; localStorage.setItem('atendia-profile-done', '1')"
                >
                    <x-icon name="x" :size="14" />
                </button>
            </div>
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
