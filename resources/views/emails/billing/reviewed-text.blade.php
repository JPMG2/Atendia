{{ __($copy.'.title') }}

{{ __($copy.'.intro', ['name' => $model->business?->name, 'until' => $model->period_ends_at?->format('d/m/Y'), 'reason' => $model->rejection_reason]) }}

{{ $model->formattedAmount() }}

{{ __($copy.'.body') }}
{{ __('mail.billing.cta_history') }}: {{ route('my-payments') }}

{{ __('mail.billing.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
