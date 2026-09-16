{{ __('mail.new_device.title') }}

{{ __('mail.new_device.intro', ['name' => $model->user->name]) }}

{{ __('mail.new_device.browser') }}: {{ $model->user_agent }}
{{ __('mail.new_device.ip') }}: {{ $model->ip }}
@if ($model->location)
{{ __('mail.new_device.location') }}: {{ $model->location }}
@endif
{{ __('mail.new_device.time') }}: {{ $model->last_login_at->translatedFormat('d \d\e F \d\e Y, H:i') }} UTC

{{ __('mail.new_device.body_ok') }}

{{ __('mail.new_device.body_alert') }}
{{ __('mail.new_device.not_me') }}: {{ $revokeUrl }}
{{ __('mail.new_device.cta') }}: {{ route('password.request') }}

{{ __('mail.new_device.closing') }}
{{ __('mail.new_device.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
