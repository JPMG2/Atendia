{{ __('mail.team.invitation.title', ['business' => $business]) }}

{{ $model->name !== null ? __('mail.team.invitation.intro', ['name' => $model->name]) : __('mail.team.invitation.intro_anonymous') }}

{{ $model->email }}

{{ __('mail.team.invitation.body', ['days' => $days]) }}
{{ __('mail.team.invitation.cta') }}: {{ $joinUrl }}

{{ __('mail.team.invitation.closing') }}
{{ __('mail.account.team') }}

{{ __('mail.layout.rights', ['year' => now()->year]) }}
