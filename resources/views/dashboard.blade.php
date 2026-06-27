<x-app-layout>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">Hola, {{ auth()->user()?->name }}</h1>
            <p class="page-head-sub">Así viene tu negocio hoy.</p>
        </div>

        <x-ui.button variant="primary" icon="sparkles">Crear mi asistente</x-ui.button>
    </div>

    <div class="stat-grid">
        <x-ui.stat-card label="Conversaciones hoy" value="48" delta="+12%" trend="up" icon="message-circle" tint="brand" />
        <x-ui.stat-card label="Turnos agendados" value="12" delta="+3" trend="up" icon="calendar-check" tint="info" />
        <x-ui.stat-card label="Productos vistos" value="156" delta="-4%" trend="down" icon="package" tint="warning" />
        <x-ui.stat-card label="Respuesta promedio" value="2s" delta="estable" trend="flat" icon="zap" tint="accent" />
    </div>
</x-app-layout>
