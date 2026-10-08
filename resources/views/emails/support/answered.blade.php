<x-email.layout :preheader="__('support.mail.answered_preheader', ['code' => $model->code])">
    <x-email.notice
        :eyebrow="__('support.mail.eyebrow')"
        :title="__('support.mail.answered_title', ['code' => $model->code])"
        :intro="$reply"
        :chip="__('support.kinds.'.$model->kind->value)"
        :closing="__('support.mail.answered_closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
