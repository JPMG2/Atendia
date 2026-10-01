<x-email.layout :preheader="__('support.mail.preheader', ['code' => $model->code])">
    <x-email.notice
        :eyebrow="__('support.mail.eyebrow')"
        :title="__('support.mail.title', ['code' => $model->code, 'business' => $model->business?->name])"
        :intro="$model->body"
        :chip="__('support.kinds.'.$model->kind->value)"
        :primary-url="route('admin.support')"
        :primary-label="__('support.mail.cta')"
        :closing="__('support.mail.where', [
            'screen' => $model->screen !== null
                ? (\App\Models\Menu::titleFor($model->screen) ?? $model->screen)
                : __('support.no_screen'),
        ])"
        :team="__('mail.account.team')"
    />
</x-email.layout>
