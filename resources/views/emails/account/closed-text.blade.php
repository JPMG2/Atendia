{{ __('mail.account.closed.title') }}

{{ __('mail.account.closed.intro', ['name' => $model->name]) }}

{{ __('mail.account.closed.body', ['days' => config('atendia.account_restore_days')]) }}
{{ __('mail.account.closed.cta') }}: {{ $restoreUrl }}

{{ __('mail.account.closed.alert') }}

{{ __('mail.account.closed.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
