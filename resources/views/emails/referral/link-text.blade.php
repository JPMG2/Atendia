{{ __('mail.referral_link.title') }}

{{ __('mail.referral_link.intro', ['name' => $model->name]) }}

{{ __('mail.referral_link.body', ['percent' => (int) config('atendia.referral.reward_percent'), 'days' => (int) config('atendia.referral.invited_trial_days')]) }}

{{ $model->referralLink() }}

{{ __('mail.referral_link.cta') }}: {{ route('referrals') }}

{{ __('mail.referral_link.reason') }}
