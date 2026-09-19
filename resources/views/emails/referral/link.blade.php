<x-email.layout :preheader="__('mail.referral_link.preheader')">
    <x-slot:footnote>{{ __('mail.referral_link.reason') }}</x-slot:footnote>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="background-color:#0EA47A; background:linear-gradient(135deg, #0EA47A 0%, #0A6B50 100%); padding:22px 40px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:12px; font-weight:700; letter-spacing:0.16em; text-transform:uppercase; color:#95EACB;">{{ __('mail.referral_link.eyebrow') }}</p>
                <h1 style="margin:6px 0 0; font-family:'Sora', 'Segoe UI', Arial, sans-serif; font-size:26px; line-height:1.2; font-weight:800; color:#FFFFFF;">{{ __('mail.referral_link.title') }}</h1>
                <p style="margin:8px 0 0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.6; color:#E9FBF4;">{{ __('mail.referral_link.intro', ['name' => $model->name]) }}</p>
            </td>
        </tr>
        <tr>
            <td style="padding:28px 40px 8px;">
                <p style="margin:0; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; line-height:1.7; color:#243B33;">{{ __('mail.referral_link.body', ['percent' => (int) config('atendia.referral.reward_percent'), 'days' => (int) config('atendia.referral.invited_trial_days')]) }}</p>
            </td>
        </tr>
        <tr>
            {{-- The link rides as visible text on purpose: this mail is meant
            to be FORWARDED, and a forwarded button loses trust — a plain
            link survives any client. --}}
            <td align="center" style="padding:18px 40px 6px;">
                <p style="margin:0; padding:12px 18px; background-color:#F0F7F4; border-radius:10px; font-family:'JetBrains Mono', 'Courier New', monospace; font-size:14px; line-height:1.5; color:#0A6B50; word-break:break-all;">{{ $model->referralLink() }}</p>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding:18px 40px 30px;">
                <a href="{{ route('referrals') }}" style="display:inline-block; padding:12px 28px; background-color:#0EA47A; border-radius:12px; font-family:'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif; font-size:15px; font-weight:700; color:#FFFFFF; text-decoration:none;">{{ __('mail.referral_link.cta') }}</a>
            </td>
        </tr>
    </table>
</x-email.layout>
