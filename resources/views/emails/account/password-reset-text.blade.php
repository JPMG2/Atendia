{{ __('mail.account.password_reset.title') }}

{{ __('mail.account.password_reset.intro', ['name' => $model->name]) }}

{{ __('mail.account.password_reset.body', ['minutes' => $minutes]) }}
{{ __('mail.account.password_reset.cta') }}: {{ $resetUrl }}

{{ __('mail.account.password_reset.alert') }}

{{ __('mail.account.password_changed.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
