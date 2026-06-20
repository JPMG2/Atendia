<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Atendia conecta tu WhatsApp con un asistente de IA que responde, agenda turnos y muestra tus productos. Tu negocio, atendido por IA.">

    <title>{{ $title ?? 'Atendia — Tu negocio, atendido por IA' }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/logo-mark-color.svg') }}">

    {{-- Tema antes del primer pintado: evita el flash claro→oscuro --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('atendia-theme');
                if (!t) { t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
                document.documentElement.classList.toggle('dark', t === 'dark');
            } catch (e) {}
        })();
    </script>

    <style>[x-cloak]{display:none !important;}</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    {{ $slot }}
</body>
</html>
