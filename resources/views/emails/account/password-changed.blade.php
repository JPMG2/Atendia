<x-email.layout :preheader="__('mail.account.password_changed.preheader')">
    <x-slot:footnote>{{ __('mail.account.password_changed.reason') }}</x-slot:footnote>

    <x-email.notice
        glyph="&#9888;"
        :eyebrow="__('mail.account.password_changed.eyebrow')"
        :title="__('mail.account.password_changed.title')"
        :intro="__('mail.account.password_changed.intro', ['name' => $model->name, 'time' => $model->updated_at->translatedFormat('d \d\e F \d\e Y, H:i')])"
        :body="__('mail.account.password_changed.body')"
        :alert="__('mail.account.password_changed.alert')"
        :primary-url="route('password.request')"
        :primary-label="__('mail.account.password_changed.cta')"
        :closing="__('mail.account.password_changed.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
