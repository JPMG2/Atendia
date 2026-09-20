<?php

use App\Classes\Main\Client;
use App\Classes\Main\Plan;
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
     * Menu tree of the active panel, memoized per request, with the catalog
     * badges overlaid LIVE per tenant: a static number that lies costs more
     * trust than no badge (audit, 2026-09-20). Zero shows nothing.
     *
     * @return Collection<int, Menu>
     */
    #[Computed]
    public function tree()
    {
        $tree = Menu::tree($this->panel);
        $counts = Auth::user()?->business?->offerCounts();

        if ($counts !== null) {
            $this->overlayBadges($tree, $counts);
        }

        return $tree;
    }

    /**
     * @param  Collection<int, Menu>  $items
     * @param  array<string, int>  $counts
     */
    private function overlayBadges($items, array $counts): void
    {
        foreach ($items as $item) {
            if (array_key_exists((string) $item->route_name, $counts)) {
                $item->badge = $counts[$item->route_name] > 0 ? (string) $counts[$item->route_name] : null;
            }

            $this->overlayBadges($item->childrenRecursive ?? collect(), $counts);
        }
    }

    /**
     * The real profile strength, from the one class that sees every piece.
     *
     * @return array{done: int, total: int, missing: list<string>}
     */
    #[Computed]
    public function strength(): array
    {
        return Client::for(Auth::user())->profileStrength;
    }

    #[Computed]
    public function profilePercent(): int
    {
        return (int) round($this->strength['done'] / max(1, $this->strength['total']) * 100);
    }

    /**
     * Sidebar upsell data; null hides the block: no business yet, or already
     * on the top rung with nothing left to sell.
     *
     * @return array{name: string, days: int|null}|null
     */
    #[Computed]
    public function planUpsell(): ?array
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return null;
        }

        $plan = $business->plan();
        $ladder = Plan::ladder();

        if (! $plan->isBelow(end($ladder))) {
            return null;
        }

        return [
            'name' => __('plan.names.'.$plan->code),
            'days' => $business->subscription?->trialDaysLeft(),
        ];
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

        {{-- The plan upsell talks to a CLIENT with rungs left to climb; the
        admin panel is the owner's and has no plan to improve. --}}
        @if ($panel === 'client' && $this->planUpsell !== null)
            <div class="sidebar-upsell">
                <div class="sidebar-upsell-head">
                    <x-icon name="gem" :size="16" />
                    <span>{{ __('menu.plan_name', ['plan' => $this->planUpsell['name']]) }}</span>
                </div>
                @if ($this->planUpsell['days'] !== null)
                    <p class="sidebar-upsell-text">
                        {{ __('menu.plan_trial', ['days' => $this->planUpsell['days']]) }}
                    </p>
                @endif
                <x-ui.button
                    variant="primary"
                    size="sm"
                    :href="route('my-plan')"
                    wire:navigate
                    :fullWidth="true"
                >
                    {{ __('menu.plan_cta') }}
                </x-ui.button>
            </div>
        @endif
    </div>
</nav>
