{{ __('mail.account.verify.title') }}

{{ __('mail.account.verify.intro', ['name' => $model->name]) }}

{{ $model->email }}

{{ __('mail.account.verify.body', ['minutes' => config('atendia.email_change_minutes')]) }}
{{ __('mail.account.verify.cta') }}: {{ $verifyUrl }}

{{ __('mail.account.verify.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
