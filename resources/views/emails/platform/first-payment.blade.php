<x-email.layout :preheader="__('platform.first_payment.preheader')">
    <x-email.notice
        :eyebrow="__('platform.first_payment.eyebrow')"
        :title="__('platform.first_payment.subject', ['business' => $payment->business?->name])"
        :intro="__('platform.first_payment.intro')"
        :body="__('platform.first_payment.line', [
            'business' => $payment->business?->name,
            'plan' => __('plan.names.'.$payment->plan),
            'cycle' => __('platform.first_payment.cycle_'.$payment->billing_cycle),
            'amount' => $payment->formattedAmount(),
        ])"
        :primary-url="route('admin.payments')"
        :primary-label="__('platform.first_payment.cta')"
        :closing="__('platform.first_payment.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
