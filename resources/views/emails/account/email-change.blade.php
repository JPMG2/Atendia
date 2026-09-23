<x-email.layout :preheader="__('mail.account.email_change.preheader')">
    <x-slot:footnote>{{ __('mail.account.email_change.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('mail.account.email_change.eyebrow')"
        :title="__('mail.account.email_change.title')"
        :intro="__('mail.account.email_change.intro', ['name' => $model->name])"
        :chip="$model->pending_email"
        :body="__('mail.account.email_change.body', ['minutes' => config('atendia.email_change_minutes')])"
        :alert="__('mail.account.email_change.alert')"
        :primary-url="$confirmUrl"
        :primary-label="__('mail.account.email_change.cta')"
        :closing="__('mail.account.email_change.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
