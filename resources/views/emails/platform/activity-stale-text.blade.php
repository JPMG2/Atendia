{{ __('admin.home.activity.mail.subject') }}

{{ __('admin.home.activity.mail.intro', ['since' => $since]) }}

{{ __('admin.home.activity.mail.body', ['hours' => max(1, intdiv($minutes, 60))]) }}
{{ __('admin.home.activity.mail.cta') }}: {{ route('admin.dashboard') }}

{{ __('admin.home.activity.mail.closing') }}

{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
