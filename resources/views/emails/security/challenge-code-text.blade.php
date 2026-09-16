{{ __('mail.challenge.title') }}

{{ __('mail.challenge.intro', ['name' => $user->name]) }}

{{ $code }}

{{ __('mail.challenge.body') }}

{{ __('mail.challenge.alert') }}

{{ __('mail.challenge.closing') }}
{{ __('mail.new_device.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
