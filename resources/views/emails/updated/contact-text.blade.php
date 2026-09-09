{{ __('mail.contact_updated.title') }}

{{ __('mail.contact_updated.intro', ['name' => $model->name]) }}

{{ $model->email }}

{{ __('mail.contact_updated.body') }}

{{ __('mail.contact_updated.alert') }}
{{ __('mail.contact_updated.cta') }}: {{ route('dashboard') }}

{{ __('mail.contact_updated.closing') }}
{{ __('mail.contact_updated.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
