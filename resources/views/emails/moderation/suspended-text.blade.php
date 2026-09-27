{{ __('moderation.mail.title') }}

{{ __('moderation.mail.intro', ['name' => $model->name]) }}

{{ __('moderation.mail.body') }}
{{ __('moderation.mail.cta') }}: {{ route('dashboard') }}

{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
