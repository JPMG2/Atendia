<x-email.layout :preheader="__('mail.contact_updated.preheader')">
    <x-slot:footnote>{{ __('mail.contact_updated.reason', ['name' => $model->name]) }}</x-slot:footnote>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            {{-- Same hero grammar as the welcome, in one breath: this is a
            notice, not a landing — the whole message fits above the fold. --}}
            <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%); padding:18px 40px 18px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" width="44" height="44" style="width:44px; height:44px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:19px; font-weight:800; line-height:44px; color:#FFFFFF;">&#9993;</td>
                    </tr>
                </table>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.contact_updated.eyebrow') }}</p>

                <h1 style="margin:4px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.contact_updated.title') }}</h1>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.contact_updated.intro', ['name' => $model->name]) }}</p>
            </td>
        </tr>
        <tr>
            {{-- The new address, receipt style: it IS the payload of this mail. --}}
            <td align="center" style="padding:18px 40px 0;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="border:1px solid #D5E5DE; border-radius:999px; background-color:#F2F8F5; padding:9px 22px; font-family:'JetBrains Mono', 'Courier New', monospace; font-size:14px; font-weight:700; color:#0A6B50;">{{ $model->email }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:14px 40px 0;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.contact_updated.body') }}</p>
                <p style="margin:10px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6; color:#3D4C47;">{{ __('mail.contact_updated.alert') }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:12px auto 0;">
                    <tr>
                        <td align="center" style="border:2px solid #0EA47A; border-radius:999px;">
                            <a href="{{ route('dashboard') }}" style="display:inline-block; padding:8px 18px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:13px; font-weight:800; color:#0A6B50; text-decoration:none; border-radius:999px;">{{ __('mail.contact_updated.cta') }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:16px 40px 18px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.contact_updated.closing') }}</p>
                <p style="margin:5px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#0A6B50;">{{ __('mail.contact_updated.team') }}</p>
            </td>
        </tr>
        <tr>
            {{-- A brand echo closing the card, so it ends as it opened. --}}
            <td height="6" style="height:6px; background-color:#0EA47A; background:linear-gradient(90deg, #0EA47A 0%, #25C490 100%); font-size:0; line-height:0;">&nbsp;</td>
        </tr>
    </table>
</x-email.layout>
