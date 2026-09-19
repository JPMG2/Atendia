<x-guest-layout>
    <div class="invite-card">
        <p class="eyebrow">{{ __('referrals.invite.eyebrow') }}</p>
        <h1 class="invite-title font-display">{{ __('referrals.invite.title', ['inviter' => $inviter]) }}</h1>
        <p class="invite-sub">
            {{ __('referrals.invite.sub', ['days' => (int) config('atendia.referral.invited_trial_days')]) }}
        </p>

        <ul class="invite-perks">
            <li>
                <x-icon name="check" :size="16" />
                {{ __('referrals.invite.perk_trial', ['days' => (int) config('atendia.referral.invited_trial_days')]) }}
            </li>
            <li>
                <x-icon name="check" :size="16" />
                {{ __('referrals.invite.perk_always') }}
            </li>
            <li>
                <x-icon name="check" :size="16" />
                {{ __('referrals.invite.perk_card') }}
            </li>
        </ul>

        <x-ui.button variant="primary" size="lg" :href="route('register')" :fullWidth="true">
            {{ __('referrals.invite.cta') }}
        </x-ui.button>
    </div>
</x-guest-layout>
