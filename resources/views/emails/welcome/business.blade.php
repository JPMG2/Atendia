<x-email.layout :preheader="__('mail.business_welcome.preheader')">
    <x-slot:footnote>{{ __('mail.business_welcome.reason') }}</x-slot:footnote>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            {{-- The hero holds the whole message — seal, title and intro — so
            the mail lands even if nobody scrolls past the fold. --}}
            <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%); padding:18px 40px 18px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" width="44" height="44" style="width:44px; height:44px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:19px; font-weight:800; line-height:44px; color:#FFFFFF;">&#10003;</td>
                    </tr>
                </table>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.business_welcome.eyebrow') }}</p>

                <h1 style="margin:4px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.business_welcome.title') }}</h1>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.business_welcome.intro', ['name' => $model->name]) }}</p>
            </td>
        </tr>
        <tr>
            {{-- The Firebase banner: brand tagline plus floating glyph tiles,
            staggered with uneven paddings since mail clients drop transforms. --}}
            <td style="padding:16px 16px 0;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; border-radius:14px; overflow:hidden; background-color:#0B5440; background:linear-gradient(120deg, #0A6B50 0%, #0EA47A 55%, #25C490 100%);">
                    <tr>
                        <td valign="middle" style="padding:16px 8px 16px 18px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:17px; line-height:1.4; font-weight:800; color:#FFFFFF;">{{ __('mail.business_welcome.banner') }}</td>
                        <td align="right" valign="middle" style="padding:12px 16px 12px 0; white-space:nowrap;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td valign="top" style="padding-top:16px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0"><tr><td align="center" width="34" height="34" style="width:34px; height:34px; border-radius:9px; background-color:#FFFFFF; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:800; line-height:34px; color:#0A6B50;">IA</td></tr></table>
                                    </td>
                                    <td width="8" style="width:8px; font-size:0;">&nbsp;</td>
                                    <td valign="top">
                                        <table role="presentation" cellpadding="0" cellspacing="0"><tr><td align="center" width="34" height="34" style="width:34px; height:34px; border-radius:9px; background-color:#FFFFFF; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; line-height:34px; color:#0A6B50;">&#9993;</td></tr></table>
                                    </td>
                                    <td width="8" style="width:8px; font-size:0;">&nbsp;</td>
                                    <td valign="top" style="padding-top:26px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0"><tr><td align="center" width="34" height="34" style="width:34px; height:34px; border-radius:9px; background-color:#FFFFFF; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; line-height:34px; color:#0A6B50;">&#9990;</td></tr></table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:18px 40px 22px;">
                {{-- One activation CTA above the fold: the benefit cards below
                sell, but only this action makes the assistant real. --}}
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.business_welcome.next') }}</p>

                <table role="presentation" cellpadding="0" cellspacing="0" style="margin:14px auto 0;">
                    <tr>
                        <td align="center" style="border-radius:12px; background-color:#0EA47A;">
                            <a href="{{ route('onboarding') }}" style="display:inline-block; padding:14px 34px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#FFFFFF; text-decoration:none; border-radius:12px;">{{ __('mail.business_welcome.cta') }}</a>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
        <tr>
            {{-- The sell, Firebase-style: after the single activation CTA, two
            benefit cards make the purchase feel big — the ad, not the manual.
            The owner asked for this pair on 2026-09-04. --}}
            <td style="background-color:#F2F8F5; border-top:1px solid #EAF2EE; padding:18px 28px 20px;">
                <h2 style="margin:0; text-align:center; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:19px; line-height:1.3; font-weight:800; color:#14211D;">{{ __('mail.business_welcome.gains_title') }}</h2>
                <p style="margin:6px 0 0; text-align:center; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:14px; line-height:1.6; color:#3D4C47;">{{ __('mail.business_welcome.gains_intro') }}</p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
                    <tr>
                        <td width="50%" valign="top" style="padding-right:8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; background-color:#FFFFFF; border:1px solid #DCE7E2; border-radius:14px; overflow:hidden;">
                                <tr>
                                    <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #25C490 0%, #0A6B50 100%); padding:12px 0 10px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" width="42" height="42" style="width:42px; height:42px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; line-height:42px; color:#FFFFFF;">24</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:12px 14px 14px;">
                                        <h3 style="margin:0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#14211D;">{{ __('mail.business_welcome.gain_always_title') }}</h3>
                                        <p style="margin:6px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6; color:#3D4C47;">{{ __('mail.business_welcome.gain_always_body') }}</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px auto 0;">
                                            <tr>
                                                <td align="center" style="border:2px solid #0EA47A; border-radius:999px;">
                                                    <a href="{{ route('onboarding') }}" style="display:inline-block; padding:8px 18px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:13px; font-weight:800; color:#0A6B50; text-decoration:none; border-radius:999px;">{{ __('mail.business_welcome.gain_always_cta') }}</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                        <td width="50%" valign="top" style="padding-left:8px;">
                            {{-- Deep jade for the second header: the owner vetoed
                            coral and blue, so the pair contrasts by value, not hue. --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; background-color:#FFFFFF; border:1px solid #DCE7E2; border-radius:14px; overflow:hidden;">
                                <tr>
                                    <td align="center" style="background-color:#0A6B50; background:linear-gradient(135deg, #0A6B50 0%, #0A4233 100%); padding:12px 0 10px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" width="42" height="42" style="width:42px; height:42px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:17px; font-weight:800; line-height:42px; color:#FFFFFF;">&#9993;</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:12px 14px 14px;">
                                        <h3 style="margin:0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#14211D;">{{ __('mail.business_welcome.gain_inbox_title') }}</h3>
                                        <p style="margin:6px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6; color:#3D4C47;">{{ __('mail.business_welcome.gain_inbox_body') }}</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px auto 0;">
                                            <tr>
                                                <td align="center" style="border:2px solid #0EA47A; border-radius:999px;">
                                                    <a href="{{ route('dashboard') }}" style="display:inline-block; padding:8px 18px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:13px; font-weight:800; color:#0A6B50; text-decoration:none; border-radius:999px;">{{ __('mail.business_welcome.gain_inbox_cta') }}</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:14px 40px 18px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.business_welcome.closing') }}</p>
                <p style="margin:5px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#0A6B50;">{{ __('mail.business_welcome.team') }}</p>
            </td>
        </tr>
        <tr>
            {{-- A brand echo closing the card, so it ends as it opened. --}}
            <td height="6" style="height:6px; background-color:#0EA47A; background:linear-gradient(90deg, #0EA47A 0%, #25C490 100%); font-size:0; line-height:0;">&nbsp;</td>
        </tr>
    </table>
</x-email.layout>
