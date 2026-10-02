<x-email.layout :preheader="__('adoption.mail.preheader')">
    <x-email.notice
        :eyebrow="__('adoption.mail.eyebrow')"
        :title="trans_choice('adoption.mail.subject', count($rows), ['count' => count($rows)])"
        :intro="__('adoption.mail.intro')"
        :body="implode(PHP_EOL, $lines)"
        :primary-url="route('admin.adoption')"
        :primary-label="__('adoption.mail.cta')"
        :closing="__('adoption.mail.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
