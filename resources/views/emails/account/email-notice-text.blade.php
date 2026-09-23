{{ __('mail.account.email_notice.title') }}

{{ __('mail.account.email_notice.intro', ['name' => $model->name]) }}

{{ $newEmail }}

{{ __('mail.account.email_notice.body') }}

{{ __('mail.account.email_notice.alert') }}
{{ __('mail.account.email_notice.not_me') }}: {{ $cancelUrl }}
{{ __('mail.account.email_notice.cta') }}: {{ route('password.request') }}

{{ __('mail.account.email_notice.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
