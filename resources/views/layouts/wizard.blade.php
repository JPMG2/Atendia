<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    {{-- The default comes from translations: a PHP attribute cannot call __(). --}}
    <title>{{ $title ?? __('wizard.title') }}</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/logo-mark-color.svg') }}" />

    {{-- Theme before the first paint: it avoids the light-to-dark flash. --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('atendia-theme');
                if (!t) {
                    t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
                }
                document.documentElement.classList.toggle('dark', t === 'dark');
            } catch (e) {}
        })();
    </script>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>

    {{-- No app.js: Livewire brings its own Alpine and form-guard hooks onto it. --}}
    @vite(['resources/css/app.css', 'resources/js/form-guard.js', 'resources/js/dialog.js', 'resources/js/combobox.js', 'resources/js/file-field.js', 'resources/js/phone-field.js', 'resources/js/ws-phone.js', 'resources/js/livewire-failures.js'])
    @livewireStyles
</head>
{{-- data-*: config for livewire-failures.js — JS can resolve neither routes
nor translations, and the graceful flag stays off with debug on (there the
Livewire overlay with the stack trace is the useful thing to see). --}}
<body
    class="bg-page"
    data-login-url="{{ route('login', ['expired' => 1]) }}"
    data-fail-title="{{ __('errors.action_failed.title') }}"
    data-fail-message="{{ __('errors.action_failed.message') }}"
    @unless (config('app.debug')) data-graceful-failures @endunless
>
    {{ $slot }}

    <livewire:toast />
    <livewire:dialog />
    @livewireScripts
</body>
</html>
