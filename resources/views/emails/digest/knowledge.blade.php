<x-email.layout :preheader="__('mail.knowledge_digest.preheader')">
    <x-slot:footnote>{{ __('mail.knowledge_digest.reason', ['business' => $model->name]) }}</x-slot:footnote>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%); padding:18px 40px 20px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.knowledge_digest.eyebrow') }}</p>
                <h1 style="margin:4px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.knowledge_digest.title') }}</h1>
                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.knowledge_digest.intro', ['business' => $model->name]) }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:18px 32px 0;">
                @if ($taught > 0)
                    <p style="margin:0 0 10px; padding:12px 14px; background-color:#F2F8F5; border-radius:12px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:14px; line-height:1.6; color:#0A6B50;">{{ trans_choice('mail.knowledge_digest.taught', $taught, ['count' => $taught]) }}</p>
                @endif
                @if ($missed['count'] > 0)
                    <p style="margin:0; padding:12px 14px; background-color:#F2F8F5; border-radius:12px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:14px; line-height:1.6; color:#3D4C47;">{{ trans_choice('mail.knowledge_digest.missed', $missed['count'], ['count' => $missed['count'], 'question' => (string) $missed['top']]) }}</p>
                @endif
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:20px 40px 8px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="border-radius:12px; background-color:#0EA47A;">
                            <a href="{{ route('assistant') }}" style="display:inline-block; padding:14px 34px; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:800; color:#FFFFFF; text-decoration:none; border-radius:12px;">{{ __('mail.knowledge_digest.cta') }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:8px 40px 18px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#3D4C47;">{{ __('mail.knowledge_digest.closing') }}</p>
            </td>
        </tr>
        <tr>
            <td height="6" style="height:6px; background-color:#0EA47A; background:linear-gradient(90deg, #0EA47A 0%, #25C490 100%); font-size:0; line-height:0;">&nbsp;</td>
        </tr>
    </table>
</x-email.layout>
