<x-email.layout :preheader="__($copy.'.preheader', ['days' => $days])">
    <x-slot:footnote>{{ __('mail.billing.reason') }}</x-slot:footnote>

    <x-email.notice
        glyph="&#128276;"
        :eyebrow="__('mail.billing.eyebrow')"
        :title="__($copy.'.title', ['days' => $days])"
        :intro="__($copy.'.intro', ['name' => $model->name, 'plan' => $plan, 'date' => $date, 'days' => $days])"
        :chip="$amount"
        :body="__($copy.'.body', ['days' => $days])"
        :primary-url="route('my-payments')"
        :primary-label="__('mail.billing.cta')"
        :closing="__('mail.billing.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
