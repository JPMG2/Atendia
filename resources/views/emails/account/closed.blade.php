<x-email.layout :preheader="__('mail.account.closed.preheader', ['days' => config('atendia.account_restore_days')])">
    <x-slot:footnote>{{ __('mail.account.closed.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('mail.account.closed.eyebrow')"
        :title="__('mail.account.closed.title')"
        :intro="__('mail.account.closed.intro', ['name' => $model->name])"
        :body="__('mail.account.closed.body', ['days' => config('atendia.account_restore_days')])"
        :alert="__('mail.account.closed.alert')"
        :primary-url="$restoreUrl"
        :primary-label="__('mail.account.closed.cta')"
        :closing="__('mail.account.closed.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
