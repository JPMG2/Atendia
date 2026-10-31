{{ $heading }}

{{ __('ask_admin.letter.intro', ['name' => $business->name]) }}

{{ $text }}

{{ __('ask_admin.letter.cta') }}: {{ route('dashboard') }}

{{ __('ask_admin.letter.closing') }}

{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
