<x-email.layout :preheader="__('mail.account.verify.preheader')">
    <x-slot:footnote>{{ __('mail.account.verify.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('mail.account.verify.eyebrow')"
        :title="__('mail.account.verify.title')"
        :intro="__('mail.account.verify.intro', ['name' => $model->name])"
        :chip="$model->email"
        :body="__('mail.account.verify.body', ['minutes' => config('atendia.email_change_minutes')])"
        :primary-url="$verifyUrl"
        :primary-label="__('mail.account.verify.cta')"
        :closing="__('mail.account.verify.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
