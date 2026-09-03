<x-email.layout :preheader="__('mail.business_welcome.preheader')">
    <x-slot:footnote>{{ __('mail.business_welcome.reason') }}</x-slot:footnote>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            {{-- The hero holds the whole message — seal, title and intro — so
            the mail lands even if nobody scrolls past the fold. --}}
            <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%); padding:32px 40px 30px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" width="52" height="52" style="width:52px; height:52px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:23px; font-weight:800; line-height:52px; color:#FFFFFF;">&#10003;</td>
                    </tr>
                </table>

                <p style="margin:16px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.business_welcome.eyebrow') }}</p>

                <h1 style="margin:8px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.business_welcome.title') }}</h1>

                <p style="margin:10px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.business_welcome.intro', ['name' => $model->name]) }}</p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:34px 40px 36px;">
                {{-- One next step, per the welcome-mail canon: no feature wall,
                a single action that makes the assistant real. --}}
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.business_welcome.next') }}</p>

                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px auto 0;">
                    <tr>
                        <td align="center" style="border-radius:12px; background-color:#0EA47A;">
                            <a href="{{ route('onboarding') }}" style="display:inline-block; padding:14px 34px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#FFFFFF; text-decoration:none; border-radius:12px;">{{ __('mail.business_welcome.cta') }}</a>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:30px;">
                    <tr>
                        <td align="center" style="border-top:1px solid #EAF2EE; padding-top:26px;">
                            <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.business_welcome.closing') }}</p>
                            <p style="margin:5px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#0A6B50;">{{ __('mail.business_welcome.team') }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            {{-- A brand echo closing the card, so it ends as it opened. --}}
            <td height="6" style="height:6px; background-color:#0EA47A; background:linear-gradient(90deg, #0EA47A 0%, #25C490 100%); font-size:0; line-height:0;">&nbsp;</td>
        </tr>
    </table>
</x-email.layout>
