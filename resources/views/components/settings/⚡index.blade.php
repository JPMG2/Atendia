<?php

use Illuminate\View\View;
use Livewire\Component;

/**
 * "Ajustes" — the person behind the business. Same shape as "Mi negocio":
 * the parent only orchestrates, each card is its own component with its
 * own save, and a section slug renders that single card.
 */
new class extends Component
{
    public ?string $section = null;

    /**
     * Slug → child component. Only route-baked slugs ever arrive, so an
     * unknown key simply falls back to the full page.
     *
     * @return array<string, string>
     */
    public function sections(): array
    {
        return [
            'perfil' => 'settings.section-profile',
            'correo' => 'settings.section-email',
            'contrasena' => 'settings.section-password',
            'dos-pasos' => 'settings.section-two-factor',
            'dispositivos' => 'settings.section-devices',
            'actividad' => 'settings.section-activity',
            'cuenta' => 'settings.section-close',
        ];
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('settings.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div class="bp-head">
            <a
                href="{{ $section ? route('settings') : route('dashboard') }}"
                wire:navigate
                class="bp-back"
                aria-label="{{ __('settings.back') }}"
            >
                <x-icon name="chevron-left" :size="18" />
            </a>
            <x-ui.avatar :name="auth()->user()->name" :src="auth()->user()->avatarUrl()" size="lg" />
            <div>
                <h1 class="page-head-title">{{ __('settings.title') }}</h1>
                <p class="page-head-sub">{{ __('settings.sub') }}</p>
            </div>
        </div>
    </div>

    <div class="bp-layout">
        <div class="bp-main">
            @if ($section !== null && isset($this->sections()[$section]))
                @livewire($this->sections()[$section])
            @else
                @foreach ($this->sections() as $slug => $component)
                    @livewire($component, [], key($slug))
                @endforeach
            @endif
        </div>

        <aside class="bp-rail">
            <x-settings.security-checkup :user="auth()->user()" />

            @if ($section === null)
                <x-ui.card class="bp-card">
                    <p class="eyebrow mb-2">{{ __('settings.on_this_page') }}</p>
                    <nav class="st-nav">
                        @foreach (array_keys($this->sections()) as $slug)
                            <a href="#{{ $slug }}" @class(['is-danger' => $slug === 'cuenta'])>
                                {{ __("settings.nav.{$slug}") }}
                            </a>
                        @endforeach
                    </nav>
                </x-ui.card>
            @endif
        </aside>
    </div>
</div>
