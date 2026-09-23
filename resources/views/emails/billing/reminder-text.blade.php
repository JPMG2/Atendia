{{ __($copy.'.title', ['days' => $days]) }}

{{ __($copy.'.intro', ['name' => $model->name, 'plan' => $plan, 'date' => $date, 'days' => $days]) }}

{{ $amount }}

{{ __($copy.'.body', ['days' => $days]) }}
{{ __('mail.billing.cta') }}: {{ route('my-payments') }}

{{ __('mail.billing.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
