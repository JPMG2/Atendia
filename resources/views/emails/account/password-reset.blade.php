<x-email.layout :preheader="__('mail.account.password_reset.preheader')">
    <x-slot:footnote>{{ __('mail.account.password_reset.reason') }}</x-slot:footnote>

    <x-email.notice
        glyph="&#9888;"
        :eyebrow="__('mail.account.password_reset.eyebrow')"
        :title="__('mail.account.password_reset.title')"
        :intro="__('mail.account.password_reset.intro', ['name' => $model->name])"
        :body="__('mail.account.password_reset.body', ['minutes' => $minutes])"
        :alert="__('mail.account.password_reset.alert')"
        :primary-url="$resetUrl"
        :primary-label="__('mail.account.password_reset.cta')"
        :closing="__('mail.account.password_changed.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
