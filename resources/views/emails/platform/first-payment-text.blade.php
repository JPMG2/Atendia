{{ __('platform.first_payment.subject', ['business' => $payment->business?->name]) }}

{{ __('platform.first_payment.intro') }}

{{ __('platform.first_payment.line', [
    'business' => $payment->business?->name,
    'plan' => __('plan.names.'.$payment->plan),
    'cycle' => __('platform.first_payment.cycle_'.$payment->billing_cycle),
    'amount' => $payment->formattedAmount(),
]) }}

{{ __('platform.first_payment.cta') }}: {{ route('admin.payments') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
