{{ __('platform.new_business.subject', ['business' => $business->name]) }}

{{ __('platform.new_business.intro') }}

{{ __('platform.new_business.line', [
    'business' => $business->name,
    'email' => $business->billing_email,
    'country' => $business->country?->name ?? __('platform.new_business.no_country'),
]) }}

{{ __('platform.new_business.cta') }}: {{ route('admin.businesses', ['negocio' => $business->id]) }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
