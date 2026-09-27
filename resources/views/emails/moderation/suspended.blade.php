<x-email.layout :preheader="__('moderation.mail.preheader')">
    <x-slot:footnote>{{ __('moderation.mail.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('moderation.mail.eyebrow')"
        :title="__('moderation.mail.title')"
        :intro="__('moderation.mail.intro', ['name' => $model->name])"
        :body="__('moderation.mail.body')"
        :primary-url="route('dashboard')"
        :primary-label="__('moderation.mail.cta')"
        :closing="__('moderation.mail.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
