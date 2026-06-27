<?php

use App\Models\Menu;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /**
     * Panel activo (admin | client). Se fija en mount desde la ruta para que
     * persista en los updates de Livewire (no depender del request en vivo).
     */
    public string $panel = 'client';

    public function mount(): void
    {
        $this->panel = request()->routeIs('admin.*') ? 'admin' : 'client';
    }

    /**
     * Árbol de menú del panel activo (memoizado por request).
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

        <div class="sidebar-upsell">
            <div class="sidebar-upsell-head">
                <x-icon name="zap" :size="16" />
                <span>{{ __('menu.plan_name') }}</span>
            </div>
            <p class="sidebar-upsell-text">{{ __('menu.plan_trial') }}</p>
            <x-ui.button variant="primary" size="sm" :fullWidth="true">{{ __('menu.plan_cta') }}</x-ui.button>
        </div>
    </div>
</nav>
