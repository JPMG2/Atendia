{{ __('mail.account.email_updated.title') }}

{{ __('mail.account.email_updated.intro', ['name' => $model->name]) }}

{{ $model->email }}

{{ __('mail.account.email_updated.body') }}

{{ __('mail.account.email_updated.alert') }}
{{ __('mail.account.email_updated.cta') }}: {{ route('settings') }}

{{ __('mail.account.email_updated.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
