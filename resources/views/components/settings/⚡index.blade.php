<?php

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
        $sections = [
            'perfil' => 'settings.section-profile',
            'correo' => 'settings.section-email',
            'contrasena' => 'settings.section-password',
            'dos-pasos' => 'settings.section-two-factor',
            'dispositivos' => 'settings.section-devices',
            'avisos' => 'settings.section-bell',
            'actividad' => 'settings.section-activity',
            'cuenta' => 'settings.section-close',
        ];

        // Closing the account deletes the business: an agent never sees that door.
        if (auth()->user()?->isAgent()) {
            unset($sections['cuenta']);
        }

        return $sections;
    }
};
?>

<div>
    <x-ui.page-head
        :title="__('settings.title')"
        :sub="__('settings.sub')"
        :back="$section ? route('settings') : route('dashboard')"
        :backLabel="__('settings.back')"
    >
        <x-slot:lead>
            <x-ui.avatar :name="auth()->user()->name" :src="auth()->user()->avatarUrl()" size="lg" />
        </x-slot:lead>
    </x-ui.page-head>

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

        <aside class="bp-rail bp-rail-lead">
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
