<x-mail::message>
# {{ __('mail.new_company.greeting') }}

{{ __('mail.new_company.intro') }}

{{-- A panel and not a table: the mail renders in clients that collapse a
two-column table into one, and the data would come out interleaved. --}}
<x-mail::panel>
**{{ $model->legal_name }}**

{{ __('mail.new_company.tax_id') }}: {{ $model->tax_id }}
@if (filled($model->address))

{{ __('mail.new_company.address') }}: {{ $model->address }}
@endif
</x-mail::panel>

<x-mail::button :url="route('admin.company')">
{{ __('mail.new_company.action') }}
</x-mail::button>

{{ __('mail.new_company.outro') }}

{{ __('mail.new_company.salutation') }}<br>
{{ config('app.name') }}
</x-mail::message>
