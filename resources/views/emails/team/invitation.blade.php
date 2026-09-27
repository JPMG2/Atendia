<x-email.layout :preheader="__('mail.team.invitation.preheader', ['business' => $business])">
    <x-slot:footnote>{{ __('mail.team.invitation.reason', ['business' => $business]) }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('mail.team.invitation.eyebrow')"
        :title="__('mail.team.invitation.title', ['business' => $business])"
        :intro="$model->name !== null ? __('mail.team.invitation.intro', ['name' => $model->name]) : __('mail.team.invitation.intro_anonymous')"
        :chip="$model->email"
        :body="__('mail.team.invitation.body', ['days' => $days])"
        :primary-url="$joinUrl"
        :primary-label="__('mail.team.invitation.cta')"
        :closing="__('mail.team.invitation.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
