{{ __('mail.account.email_change.title') }}

{{ __('mail.account.email_change.intro', ['name' => $model->name]) }}

{{ $model->pending_email }}

{{ __('mail.account.email_change.body', ['minutes' => config('atendia.email_change_minutes')]) }}
{{ __('mail.account.email_change.cta') }}: {{ $confirmUrl }}

{{ __('mail.account.email_change.alert') }}

{{ __('mail.account.email_change.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
