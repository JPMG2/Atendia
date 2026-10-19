{{ __('mail.knowledge_digest.title') }}

{{ __('mail.knowledge_digest.intro', ['business' => $model->name]) }}

@if ($taught > 0)
{{ trans_choice('mail.knowledge_digest.taught', $taught, ['count' => $taught]) }}
@endif
@if ($missed['count'] > 0)
{{ trans_choice('mail.knowledge_digest.missed', $missed['count'], ['count' => $missed['count'], 'question' => (string) $missed['top']]) }}
@endif

{{ __('mail.knowledge_digest.cta') }}: {{ route('assistant') }}

{{ __('mail.knowledge_digest.closing') }}

{{ __('mail.layout.rights', ['year' => now()->year, 'brand' => \App\Models\Company::brand()]) }}
