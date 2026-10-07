<x-email.layout :preheader="__('incidents.mail.preheader')">
    <x-email.notice
        :eyebrow="__('incidents.mail.eyebrow')"
        :title="trans_choice('incidents.mail.subject', count($rows), ['count' => count($rows)])"
        :intro="__('incidents.mail.intro')"
        :body="implode(PHP_EOL, $lines)"
        :primary-url="route('admin.incidents')"
        :primary-label="__('incidents.mail.cta')"
        :closing="__('incidents.mail.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
