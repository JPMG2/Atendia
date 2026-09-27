<x-email.layout :preheader="__('moderation.appeal.mail_preheader')">
    <x-slot:footnote>{{ __('moderation.alert.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('moderation.alert.eyebrow')"
        :title="__('moderation.appeal.mail_title', ['business' => $model->name])"
        :intro="__('moderation.appeal.mail_intro')"
        :body="$model->appeal_message"
        :primary-url="route('admin.moderation')"
        :primary-label="__('moderation.alert.cta')"
        :closing="__('moderation.alert.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
