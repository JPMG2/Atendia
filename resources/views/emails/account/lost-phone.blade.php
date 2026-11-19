<x-email.layout :preheader="__($key.'.preheader')">
    <x-slot:footnote>{{ __('mail.account.lost_phone.reason') }}</x-slot:footnote>

    <x-email.notice
        glyph="&#9888;"
        :eyebrow="__('mail.account.password_changed.eyebrow')"
        :title="__($key.'.title')"
        :intro="__($key.'.intro', ['name' => $model->name, 'time' => $time])"
        :body="__($key.'.body')"
        :alert="__($key.'.alert')"
        :list-title="__('whatsapp.lost_phone.cut_title')"
        :lines="$cutLines"
        :primary-url="$ctaUrl"
        :primary-label="__($key.'.cta')"
        :closing="__('mail.account.password_changed.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
