<x-email.layout :preheader="__('admin.ai_usage.mail.preheader')">
    <x-email.notice
        :eyebrow="__('admin.ai_usage.mail.eyebrow')"
        :title="trans_choice('admin.ai_usage.mail.subject', count($rows), ['count' => count($rows)])"
        :intro="__('admin.ai_usage.mail.intro', ['month' => $month])"
        :body="implode(PHP_EOL, $lines)"
        :primary-url="route('admin.ai-usage')"
        :primary-label="__('admin.ai_usage.mail.cta')"
        :closing="__('admin.ai_usage.mail.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
