<x-email.layout :preheader="__('admin.home.activity.mail.preheader')">
    <x-email.notice
        :eyebrow="__('admin.home.activity.mail.eyebrow')"
        :title="__('admin.home.activity.mail.subject')"
        :intro="__('admin.home.activity.mail.intro', ['since' => $since])"
        :body="__('admin.home.activity.mail.body', ['hours' => max(1, intdiv($minutes, 60))])"
        :primary-url="route('admin.dashboard')"
        :primary-label="__('admin.home.activity.mail.cta')"
        :closing="__('admin.home.activity.mail.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
