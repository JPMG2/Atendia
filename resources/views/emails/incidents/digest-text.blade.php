{{ trans_choice('incidents.mail.subject', count($rows), ['count' => count($rows)]) }}

{{ __('incidents.mail.intro') }}

@foreach ($lines as $line)
{{ $line }}
@endforeach

{{ __('incidents.mail.cta') }}: {{ route('admin.incidents') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
