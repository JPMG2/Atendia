{{ __($key.'.title') }}

{{ __($key.'.intro', ['name' => $model->name, 'time' => $time]) }}

{{ __($key.'.body') }}

{{ __($key.'.alert') }}
{{ __($key.'.cta') }}: {{ $paused ? route('whatsapp') : route('password.request') }}

{{ __('mail.account.password_changed.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
