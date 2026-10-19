{{ __('mail.daily_digest.title') }}

{{ __('mail.daily_digest.intro', ['business' => $digest->business]) }}

{{ __('mail.daily_digest.messages') }}: {{ $digest->messages }}
{{ __('mail.daily_digest.contacts') }}: {{ $digest->contacts }}
@if ($digest->appointmentsTomorrow > 0)
{{ __('mail.daily_digest.tomorrow') }}: {{ $digest->appointmentsTomorrow }}
@endif
@if ($digest->summary !== '')

{{ __('mail.daily_digest.summary_title') }}
{{ $digest->summary }}
@endif
@if ($digest->questions !== [])

{{ __('mail.daily_digest.questions_title') }}
@foreach ($digest->questions as $question)
- {{ $question }}
@endforeach
@endif
@if ($digest->referred > 0)

{{ trans_choice('mail.daily_digest.referrals', $digest->referred, ['count' => $digest->referred]) }}
@endif

{{ __('mail.daily_digest.cta') }}: {{ route('conversations') }}
{{ __('mail.daily_digest.ask') }}

{{ __('mail.daily_digest.closing') }}

{{ __('mail.layout.rights', ['year' => now()->year, 'brand' => \App\Models\Company::brand()]) }}
