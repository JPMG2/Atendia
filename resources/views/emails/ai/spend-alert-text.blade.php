{{ trans_choice('admin.ai_usage.mail.subject', count($rows), ['count' => count($rows)]) }}

{{ __('admin.ai_usage.mail.intro', ['month' => $month]) }}

@foreach ($lines as $line)
{{ $line }}
@endforeach

{{ __('admin.ai_usage.mail.cta') }}: {{ route('admin.ai-usage') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
