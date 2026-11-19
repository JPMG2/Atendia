{{ __($key.'.title') }}

{{ __($key.'.intro', ['name' => $model->name, 'time' => $time]) }}

{{ __($key.'.body') }}
@if ($cutLines !== [])

{{ __('whatsapp.lost_phone.cut_title') }}
@foreach ($cutLines as $line)
- {{ $line }}
@endforeach
@endif

{{ __($key.'.alert') }}
{{ __($key.'.cta') }}: {{ $ctaUrl }}

{{ __('mail.account.password_changed.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
