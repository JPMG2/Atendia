{{ trans_choice('adoption.mail.subject', count($rows), ['count' => count($rows)]) }}

{{ __('adoption.mail.intro') }}

@foreach ($lines as $line)
{{ $line }}
@endforeach

{{ __('adoption.mail.cta') }}: {{ route('admin.adoption') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
