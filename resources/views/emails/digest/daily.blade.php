<x-email.layout :preheader="__('mail.daily_digest.preheader')">
    <x-slot:footnote>{{ __('mail.daily_digest.reason', ['business' => $digest->business]) }}</x-slot:footnote>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%); padding:18px 40px 20px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.daily_digest.eyebrow') }}</p>
                <h1 style="margin:4px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.daily_digest.title') }}</h1>
                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.daily_digest.intro', ['business' => $digest->business]) }}</p>
            </td>
        </tr>
        <tr>
            {{-- Three figures, each one read from the base: a tile that cannot be
            computed is not drawn, so a quiet day shows fewer tiles, never a made-up zero. --}}
            <td style="padding:18px 24px 6px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        @php
                            $tiles = [
                                [$digest->messages, __('mail.daily_digest.messages')],
                                [$digest->contacts, __('mail.daily_digest.contacts')],
                            ];

                            if ($digest->appointmentsTomorrow > 0) {
                                $tiles[] = [$digest->appointmentsTomorrow, __('mail.daily_digest.tomorrow')];
                            }
                        @endphp
                        @foreach ($tiles as [$figure, $label])
                            <td width="{{ (int) floor(100 / count($tiles)) }}%" valign="top" style="padding:0 4px;">
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; background-color:#F2F8F5; border:1px solid #DCE7E2; border-radius:14px;">
                                    <tr>
                                        <td align="center" style="padding:14px 6px;">
                                            <p style="margin:0; font-family:'JetBrains Mono', 'Courier New', monospace; font-size:28px; line-height:1.1; font-weight:800; color:#0A6B50;">{{ $figure }}</p>
                                            <p style="margin:6px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; line-height:1.4; color:#3D4C47;">{{ $label }}</p>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        @endforeach
                    </tr>
                </table>
            </td>
        </tr>

        @if ($digest->summary !== '')
            <tr>
                <td style="padding:14px 32px 0;">
                    <h2 style="margin:0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:16px; font-weight:800; color:#14211D;">{{ __('mail.daily_digest.summary_title') }}</h2>
                    <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:14px; line-height:1.7; color:#3D4C47; white-space:pre-line;">{{ $digest->summary }}</p>
                </td>
            </tr>
        @endif

        @if ($digest->questions !== [])
            <tr>
                <td style="padding:16px 32px 0;">
                    <h2 style="margin:0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:16px; font-weight:800; color:#14211D;">{{ __('mail.daily_digest.questions_title') }}</h2>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;">
                        @foreach ($digest->questions as $question)
                            <tr>
                                <td style="padding:7px 0; border-top:1px solid #EAF2EE; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:14px; line-height:1.5; color:#3D4C47;">{{ $question }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr>
        @endif

        @if ($digest->referred > 0)
            <tr>
                <td style="padding:16px 32px 0;">
                    <p style="margin:0; padding:12px 14px; background-color:#F2F8F5; border-radius:12px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:14px; line-height:1.6; color:#0A6B50;">{{ trans_choice('mail.daily_digest.referrals', $digest->referred, ['count' => $digest->referred]) }}</p>
                </td>
            </tr>
        @endif

        <tr>
            <td align="center" style="padding:20px 40px 8px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="border-radius:12px; background-color:#0EA47A;">
                            <a href="{{ route('conversations') }}" style="display:inline-block; padding:14px 34px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#FFFFFF; text-decoration:none; border-radius:12px;">{{ __('mail.daily_digest.cta') }}</a>
                        </td>
                    </tr>
                </table>
                <p style="margin:14px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:13px; line-height:1.6; color:#5B6B65;">{{ __('mail.daily_digest.ask') }}</p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:6px 40px 18px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.daily_digest.closing') }}</p>
            </td>
        </tr>
        <tr>
            <td height="6" style="height:6px; background-color:#0EA47A; background:linear-gradient(90deg, #0EA47A 0%, #25C490 100%); font-size:0; line-height:0;">&nbsp;</td>
        </tr>
    </table>
</x-email.layout>
