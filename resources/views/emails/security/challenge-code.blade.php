<x-email.layout :preheader="__('mail.challenge.preheader')">
    <x-slot:footnote>{{ __('mail.challenge.reason') }}</x-slot:footnote>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%); padding:18px 40px 18px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" width="44" height="44" style="width:44px; height:44px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:19px; font-weight:800; line-height:44px; color:#FFFFFF;">&#128273;</td>
                    </tr>
                </table>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.challenge.eyebrow') }}</p>

                <h1 style="margin:4px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.challenge.title') }}</h1>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.challenge.intro', ['name' => $user->name]) }}</p>
            </td>
        </tr>
        <tr>
            {{-- The code IS this mail: huge, mono, copy-friendly. --}}
            <td align="center" style="padding:20px 40px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="border:1px solid #D5E5DE; border-radius:14px; background-color:#F2F8F5; padding:14px 34px; font-family:'JetBrains Mono', 'Courier New', monospace; font-size:32px; font-weight:700; letter-spacing:0.35em; color:#0A6B50;">{{ $code }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:14px 40px 0;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.challenge.body') }}</p>
                <p style="margin:10px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6; color:#3D4C47;">{{ __('mail.challenge.alert') }}</p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:16px 40px 18px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.challenge.closing') }}</p>
                <p style="margin:5px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#0A6B50;">{{ __('mail.new_device.team') }}</p>
            </td>
        </tr>
        <tr>
            <td height="6" style="height:6px; background-color:#0EA47A; background:linear-gradient(90deg, #0EA47A 0%, #25C490 100%); font-size:0; line-height:0;">&nbsp;</td>
        </tr>
    </table>
</x-email.layout>
