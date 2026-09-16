<x-email.layout :preheader="__('mail.new_device.preheader')">
    <x-slot:footnote>{{ __('mail.new_device.reason') }}</x-slot:footnote>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Same hero grammar as the other notices: this is a security
            note, not a landing — the whole message fits above the fold. --}}
            <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%); padding:18px 40px 18px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" width="44" height="44" style="width:44px; height:44px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:19px; font-weight:800; line-height:44px; color:#FFFFFF;">&#9888;</td>
                    </tr>
                </table>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.new_device.eyebrow') }}</p>

                <h1 style="margin:4px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.new_device.title') }}</h1>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.new_device.intro', ['name' => $model->user->name]) }}</p>
            </td>
        </tr>
        <tr>
            {{-- The access, receipt style: it IS the payload of this mail. --}}
            <td align="center" style="padding:18px 40px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #D5E5DE; border-radius:12px; background-color:#F2F8F5;">
                    <tr>
                        <td style="padding:12px 18px 4px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; color:#3D4C47;">{{ __('mail.new_device.browser') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 18px 10px; font-family:'JetBrains Mono', 'Courier New', monospace; font-size:12px; line-height:1.5; color:#0A6B50; word-break:break-all;">{{ $model->user_agent }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 18px 4px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; color:#3D4C47;">{{ __('mail.new_device.ip') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 18px 10px; font-family:'JetBrains Mono', 'Courier New', monospace; font-size:13px; font-weight:700; color:#0A6B50;">{{ $model->ip }}</td>
                    </tr>
                    @if ($model->location)
                        <tr>
                            <td style="padding:0 18px 4px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; color:#3D4C47;">{{ __('mail.new_device.location') }}</td>
                        </tr>
                        <tr>
                            <td style="padding:0 18px 10px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; font-weight:700; color:#0A6B50;">{{ $model->location }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:0 18px 4px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; color:#3D4C47;">{{ __('mail.new_device.time') }}</td>
                    </tr>
                    <tr>
                        <td style="padding:0 18px 12px; font-family:'JetBrains Mono', 'Courier New', monospace; font-size:13px; font-weight:700; color:#0A6B50;">{{ $model->last_login_at->translatedFormat('d \d\e F \d\e Y, H:i') }} UTC</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:14px 40px 0;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.new_device.body_ok') }}</p>
                <p style="margin:10px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6; color:#3D4C47;">{{ __('mail.new_device.body_alert') }}</p>

                {{-- "This wasn't me" leads: one click kicks the intruder out
                without needing a login; the password change rides second. --}}
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px auto 0;">
                    <tr>
                        <td align="center" style="border-radius:999px; background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%);">
                            <a href="{{ $revokeUrl }}" style="display:inline-block; padding:10px 22px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:13px; font-weight:800; color:#FFFFFF; text-decoration:none; border-radius:999px;">{{ __('mail.new_device.not_me') }}</a>
                        </td>
                    </tr>
                </table>
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px auto 0;">
                    <tr>
                        <td align="center" style="border:2px solid #0EA47A; border-radius:999px;">
                            <a href="{{ route('password.request') }}" style="display:inline-block; padding:8px 18px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:13px; font-weight:800; color:#0A6B50; text-decoration:none; border-radius:999px;">{{ __('mail.new_device.cta') }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:16px 40px 18px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.new_device.closing') }}</p>
                <p style="margin:5px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#0A6B50;">{{ __('mail.new_device.team') }}</p>
            </td>
        </tr>
        <tr>
            {{-- A brand echo closing the card, so it ends as it opened. --}}
            <td height="6" style="height:6px; background-color:#0EA47A; background:linear-gradient(90deg, #0EA47A 0%, #25C490 100%); font-size:0; line-height:0;">&nbsp;</td>
        </tr>
    </table>
</x-email.layout>
