<x-email.layout :preheader="__($copy.'.preheader')">
    <x-slot:footnote>{{ __('mail.account.two_factor_reason') }}</x-slot:footnote>

    <x-email.notice
        glyph="&#9888;"
        :eyebrow="__('mail.account.two_factor_eyebrow')"
        :title="__($copy.'.title')"
        :intro="__($copy.'.intro', ['name' => $model->name])"
        :body="__($copy.'.body')"
        :alert="__('mail.account.two_factor_alert')"
        :primary-url="route('password.request')"
        :primary-label="__('mail.account.two_factor_cta')"
        :closing="__('mail.account.password_changed.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
