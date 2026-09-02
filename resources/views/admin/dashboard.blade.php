<x-app-layout>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">Configuración</h1>
            <p class="page-head-sub">Administrá la plataforma de Atendia.</p>
        </div>
    </div>

    @php
        // Settings tiles (skeleton; each area gets wired when its turn comes).
        $tiles = [
            ['icon' => 'users', 'title' => 'Usuarios', 'desc' => 'Gestioná los usuarios y sus accesos.'],
            ['icon' => 'workflow', 'title' => 'Integraciones', 'desc' => 'Conectá WhatsApp, n8n y más.', 'route' => 'admin.integrations'],
            ['icon' => 'sparkles', 'title' => 'Inteligencia artificial', 'desc' => 'Asistente y proveedor de IA.'],
            ['icon' => 'star', 'title' => 'Planes y facturación', 'desc' => 'Planes, suscripciones y facturación.'],
            ['icon' => 'shield-check', 'title' => 'Seguridad', 'desc' => 'Roles, permisos y auditoría.'],
            ['icon' => 'sliders-horizontal', 'title' => 'General', 'desc' => 'Preferencias generales de la plataforma.'],
        ];
    @endphp

    <div class="settings-grid">
        @foreach ($tiles as $tile)
            {{-- A tile with a route is live and navigates; the rest stay the
            skeleton they are until their turn comes. A null href is simply
            omitted, and wire:navigate is inert outside an anchor. --}}
            <x-ui.card interactive class="settings-tile" :as="isset($tile['route']) ? 'a' : 'div'"
                :href="isset($tile['route']) ? route($tile['route']) : null" wire:navigate>
                <span class="settings-tile-icon"><x-icon :name="$tile['icon']" :size="22" /></span>
                <h3 class="settings-tile-title">{{ $tile['title'] }}</h3>
                <p class="settings-tile-desc">{{ $tile['desc'] }}</p>
            </x-ui.card>
        @endforeach
    </div>
</x-app-layout>
