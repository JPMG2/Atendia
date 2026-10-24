@php
    $detail = $model->detail !== null && $model->detail !== '' ? $model->detail : __('platform_events.kinds.'.$model->kind->value.'.hint');
    $business = $model->loadMissing('aboutBusiness')->aboutBusiness?->name;
@endphp
{{ $model->kind->label() }}

{{ $business === null ? $detail : $detail.' · '.$business }}

{{ __('platform_events.mail.chip') }}
{{ __('platform_events.mail.cta') }}: {{ route('admin.alerts') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
