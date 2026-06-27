<x-app-layout>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">Configuración</h1>
            <p class="page-head-sub">Administrá la plataforma de Atendia.</p>
        </div>
    </div>

    @php
        // Tiles de configuración (skeleton; cada área se cablea en su momento).
        $tiles = [
            ['icon' => 'users', 'title' => 'Usuarios', 'desc' => 'Gestioná los usuarios y sus accesos.'],
            ['icon' => 'workflow', 'title' => 'Integraciones', 'desc' => 'Conectá WhatsApp, n8n y más.'],
            ['icon' => 'sparkles', 'title' => 'Inteligencia artificial', 'desc' => 'Asistente y proveedor de IA.'],
            ['icon' => 'star', 'title' => 'Planes y facturación', 'desc' => 'Planes, suscripciones y facturación.'],
            ['icon' => 'shield-check', 'title' => 'Seguridad', 'desc' => 'Roles, permisos y auditoría.'],
            ['icon' => 'sliders-horizontal', 'title' => 'General', 'desc' => 'Preferencias generales de la plataforma.'],
        ];
    @endphp

    <div class="settings-grid">
        @foreach ($tiles as $tile)
            <x-ui.card interactive class="settings-tile">
                <span class="settings-tile-icon"><x-icon :name="$tile['icon']" :size="22" /></span>
                <h3 class="settings-tile-title">{{ $tile['title'] }}</h3>
                <p class="settings-tile-desc">{{ $tile['desc'] }}</p>
            </x-ui.card>
        @endforeach
    </div>
</x-app-layout>
