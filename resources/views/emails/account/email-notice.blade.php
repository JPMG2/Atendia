<x-email.layout :preheader="__('mail.account.email_notice.preheader')">
    <x-slot:footnote>{{ __('mail.account.email_notice.reason') }}</x-slot:footnote>

    <x-email.notice
        glyph="&#9888;"
        :eyebrow="__('mail.account.email_notice.eyebrow')"
        :title="__('mail.account.email_notice.title')"
        :intro="__('mail.account.email_notice.intro', ['name' => $model->name])"
        :chip="$newEmail"
        :body="__('mail.account.email_notice.body')"
        :alert="__('mail.account.email_notice.alert')"
        :primary-url="$cancelUrl"
        :primary-label="__('mail.account.email_notice.not_me')"
        :secondary-url="route('password.request')"
        :secondary-label="__('mail.account.email_notice.cta')"
        :closing="__('mail.account.email_notice.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
