{{ __('mail.business_welcome.title') }}

{{ __('mail.business_welcome.intro', ['name' => $model->name]) }}

{{ __('mail.business_welcome.next') }}
{{ __('mail.business_welcome.cta') }}: {{ route('onboarding') }}

{{ __('mail.business_welcome.gains_title') }}

{{ __('mail.business_welcome.gain_always_title') }}: {{ __('mail.business_welcome.gain_always_body') }}
{{ __('mail.business_welcome.gain_always_cta') }}: {{ route('onboarding') }}

{{ __('mail.business_welcome.gain_inbox_title') }}: {{ __('mail.business_welcome.gain_inbox_body') }}
{{ __('mail.business_welcome.gain_inbox_cta') }}: {{ route('dashboard') }}

{{ __('mail.business_welcome.closing') }}
{{ __('mail.business_welcome.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
