{{ __($copy.'.title') }}

{{ __($copy.'.intro', ['name' => $model->name]) }}

{{ __($copy.'.body') }}

{{ __('mail.account.two_factor_alert') }}
{{ __('mail.account.two_factor_cta') }}: {{ route('password.request') }}

{{ __('mail.account.password_changed.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
