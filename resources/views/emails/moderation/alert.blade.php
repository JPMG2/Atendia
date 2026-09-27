<x-email.layout :preheader="__('moderation.alert.preheader')">
    <x-slot:footnote>{{ __('moderation.alert.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('moderation.alert.eyebrow')"
        :title="__('moderation.alert.title', ['business' => $model->business?->name])"
        :intro="__('moderation.alert.intro', ['source' => __('moderation.sources.'.$model->source), 'category' => $model->category, 'score' => $model->score])"
        :chip="__('moderation.severity.'.$model->severity->value)"
        :primary-url="route('admin.moderation')"
        :primary-label="__('moderation.alert.cta')"
        :closing="__('moderation.alert.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
