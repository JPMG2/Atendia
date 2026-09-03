{{ __('mail.business_welcome.title') }}

{{ __('mail.business_welcome.intro', ['name' => $model->name]) }}

{{ __('mail.business_welcome.next') }}
{{ __('mail.business_welcome.cta') }}: {{ route('onboarding') }}

{{ __('mail.business_welcome.closing') }}
{{ __('mail.business_welcome.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
