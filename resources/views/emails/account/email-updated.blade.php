<x-email.layout :preheader="__('mail.account.email_updated.preheader')">
    <x-slot:footnote>{{ __('mail.account.email_updated.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('mail.account.email_updated.eyebrow')"
        :title="__('mail.account.email_updated.title')"
        :intro="__('mail.account.email_updated.intro', ['name' => $model->name])"
        :chip="$model->email"
        :body="__('mail.account.email_updated.body')"
        :alert="__('mail.account.email_updated.alert')"
        :secondary-url="route('settings')"
        :secondary-label="__('mail.account.email_updated.cta')"
        :closing="__('mail.account.email_updated.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
