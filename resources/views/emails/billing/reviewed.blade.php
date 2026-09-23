<x-email.layout :preheader="__($copy.'.preheader')">
    <x-slot:footnote>{{ __('mail.billing.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('mail.billing.eyebrow')"
        :title="__($copy.'.title')"
        :intro="__($copy.'.intro', ['name' => $model->business?->name, 'until' => $model->period_ends_at?->format('d/m/Y'), 'reason' => $model->rejection_reason])"
        :chip="$model->formattedAmount()"
        :body="__($copy.'.body')"
        :primary-url="route('my-payments')"
        :primary-label="__('mail.billing.cta_history')"
        :closing="__('mail.billing.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
