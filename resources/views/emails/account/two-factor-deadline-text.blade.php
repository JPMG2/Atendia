{{ $deadline->isTomorrow() ? __('mail.staff_two_factor_deadline.title') : __('mail.staff_two_factor_deadline.title_soon', ['date' => $deadline->format('d/m/Y')]) }}

{{ __('mail.staff_two_factor_deadline.intro', ['name' => $model->name, 'date' => $deadline->format('d/m/Y')]) }}

{{ __('mail.staff_two_factor_deadline.body') }}

{{ __('mail.staff_two_factor_deadline.alert') }}
{{ __('mail.staff_two_factor_deadline.cta') }}: {{ route('admin.security') }}

{{ __('mail.staff_two_factor_deadline.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
