@php
    // The table stores only the region; the full location is read walking up
    // the chain. Joined here so a missing link just shortens the line.
    $location = collect([
        $model->region?->province?->country?->name,
        $model->region?->province?->name,
        $model->region?->name,
    ])->filter()->implode(' · ');
@endphp

<x-email.layout :preheader="__('mail.new_company.preheader')">
    <x-slot:footnote>{{ __('mail.new_company.reason') }}</x-slot:footnote>

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

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.new_company.eyebrow') }}</p>

                <h1 style="margin:4px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.new_company.title') }}</h1>

                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.new_company.intro', ['name' => $model->legal_name]) }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:20px 40px 20px;">
                {{-- The data reads as a receipt: label left, value right, one
                hairline per row. What a registry mail is, it looks like. --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:9px 0; border-bottom:1px solid #EAF2EE; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#7A8A84; white-space:nowrap;">{{ __('mail.new_company.legal_name') }}</td>
                        <td align="right" style="padding:9px 0 9px 24px; border-bottom:1px solid #EAF2EE; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:16px; font-weight:800; color:#14211D;">{{ $model->legal_name }}</td>
                    </tr>
                    <tr>
                        <td style="padding:9px 0; {{ filled($model->address) || filled($location) ? 'border-bottom:1px solid #EAF2EE; ' : '' }}font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#7A8A84; white-space:nowrap;">{{ __('mail.new_company.tax_id') }}</td>
                        <td align="right" style="padding:9px 0 9px 24px; {{ filled($model->address) || filled($location) ? 'border-bottom:1px solid #EAF2EE; ' : '' }}font-family:'JetBrains Mono', 'Courier New', monospace; font-size:15px; font-weight:700; color:#0A6B50;">{{ $model->tax_id }}</td>
                    </tr>
                    @if (filled($model->address))
                        <tr>
                            <td style="padding:9px 0; {{ filled($location) ? 'border-bottom:1px solid #EAF2EE; ' : '' }}font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#7A8A84; white-space:nowrap;">{{ __('mail.new_company.address') }}</td>
                            <td align="right" style="padding:9px 0 9px 24px; {{ filled($location) ? 'border-bottom:1px solid #EAF2EE; ' : '' }}font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; color:#14211D;">{{ $model->address }}</td>
                        </tr>
                    @endif

                    @if (filled($location))
                        <tr>
                            <td style="padding:9px 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase; color:#7A8A84; white-space:nowrap;">{{ __('mail.new_company.location') }}</td>
                            <td align="right" style="padding:9px 0 9px 24px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; color:#14211D;">{{ $location }}</td>
                        </tr>
                    @endif
                </table>

            </td>
        </tr>
        <tr>
            {{-- Next steps on a sunken band, the Firebase welcome pattern: the
            receipt above says what happened, these two cards say where to go
            now. Both screens are live, so neither link overpromises. --}}
            <td style="background-color:#F2F8F5; border-top:1px solid #EAF2EE; padding:18px 28px 20px;">
                <h2 style="margin:0; text-align:center; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:19px; line-height:1.3; font-weight:800; color:#14211D;">{{ __('mail.new_company.next_title') }}</h2>
                <p style="margin:6px 0 0; text-align:center; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:14px; line-height:1.6; color:#3D4C47;">{{ __('mail.new_company.next_intro') }}</p>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;">
                    <tr>
                        <td width="50%" valign="top" style="padding-right:8px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; background-color:#FFFFFF; border:1px solid #DCE7E2; border-radius:14px; overflow:hidden;">
                                <tr>
                                    <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #25C490 0%, #0A6B50 100%); padding:12px 0 10px;">
                                        <table role="presentation" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" width="42" height="42" style="width:42px; height:42px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:17px; font-weight:800; line-height:42px; color:#FFFFFF;">&#9776;</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:12px 14px 14px;">
                                        <h3 style="margin:0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#14211D;">{{ __('mail.new_company.next_catalogs_title') }}</h3>
                                        <p style="margin:6px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6; color:#3D4C47;">{{ __('mail.new_company.next_catalogs_body') }}</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px auto 0;">
                                            <tr>
                                                <td align="center" style="border:2px solid #0EA47A; border-radius:999px;">
                                                    <a href="{{ route('admin.catalogs') }}" style="display:inline-block; padding:8px 18px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:13px; font-weight:800; color:#0A6B50; text-decoration:none; border-radius:999px;">{{ __('mail.new_company.next_catalogs_cta') }}</a>
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
                                                <td align="center" width="42" height="42" style="width:42px; height:42px; border-radius:50%; background-color:rgba(255,255,255,0.16); border:1px solid rgba(255,255,255,0.4); font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:17px; font-weight:800; line-height:42px; color:#FFFFFF;">&#8646;</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="padding:12px 14px 14px;">
                                        <h3 style="margin:0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#14211D;">{{ __('mail.new_company.next_integrations_title') }}</h3>
                                        <p style="margin:6px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6; color:#3D4C47;">{{ __('mail.new_company.next_integrations_body') }}</p>
                                        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:10px auto 0;">
                                            <tr>
                                                <td align="center" style="border:2px solid #0EA47A; border-radius:999px;">
                                                    <a href="{{ route('admin.integrations') }}" style="display:inline-block; padding:8px 18px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:13px; font-weight:800; color:#0A6B50; text-decoration:none; border-radius:999px;">{{ __('mail.new_company.next_integrations_cta') }}</a>
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
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.new_company.closing') }}</p>
                <p style="margin:5px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#0A6B50;">{{ __('mail.new_company.team') }}</p>
            </td>
        </tr>
        <tr>
            {{-- A brand echo closing the card, so it ends as it opened. --}}
            <td height="6" style="height:6px; background-color:#0EA47A; background:linear-gradient(90deg, #0EA47A 0%, #25C490 100%); font-size:0; line-height:0;">&nbsp;</td>
        </tr>
    </table>
</x-email.layout>
