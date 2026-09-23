{{ __('mail.account.password_changed.title') }}

{{ __('mail.account.password_changed.intro', ['name' => $model->name, 'time' => $model->updated_at->translatedFormat('d \d\e F \d\e Y, H:i')]) }}

{{ __('mail.account.password_changed.body') }}

{{ __('mail.account.password_changed.alert') }}
{{ __('mail.account.password_changed.cta') }}: {{ route('password.request') }}

{{ __('mail.account.password_changed.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
