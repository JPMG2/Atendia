{{ __('mail.new_company.title') }}

{{ __('mail.new_company.intro', ['name' => $model->legal_name]) }}

{{ __('mail.new_company.legal_name') }}: {{ $model->legal_name }}
{{ __('mail.new_company.tax_id') }}: {{ $model->tax_id }}
@if (filled($model->address))
{{ __('mail.new_company.address') }}: {{ $model->address }}
@endif
@if (filled($location = collect([$model->region?->province?->country?->name, $model->region?->province?->name, $model->region?->name])->filter()->implode(' · ')))
{{ __('mail.new_company.location') }}: {{ $location }}
@endif

{{ __('mail.new_company.next_title') }}

{{ __('mail.new_company.next_catalogs_title') }}: {{ __('mail.new_company.next_catalogs_body') }}
{{ __('mail.new_company.next_catalogs_cta') }}: {{ route('admin.catalogs') }}

{{ __('mail.new_company.next_integrations_title') }}: {{ __('mail.new_company.next_integrations_body') }}
{{ __('mail.new_company.next_integrations_cta') }}: {{ route('admin.integrations') }}

{{ __('mail.new_company.closing') }}
{{ __('mail.new_company.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
