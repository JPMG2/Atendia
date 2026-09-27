{{ __('moderation.appeal.mail_title', ['business' => $model->name]) }}

{{ __('moderation.appeal.mail_intro') }}

{{ $model->appeal_message }}

{{ __('moderation.alert.cta') }}: {{ route('admin.moderation') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
