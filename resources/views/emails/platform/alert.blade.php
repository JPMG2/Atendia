@php
    $detail = $model->detail !== null && $model->detail !== '' ? $model->detail : __('platform_events.kinds.'.$model->kind->value.'.hint');
    $business = $model->loadMissing('aboutBusiness')->aboutBusiness?->name;
@endphp
<x-email.layout :preheader="__('platform_events.mail.preheader')">
    <x-slot:footnote>{{ __('platform_events.mail.reason') }}</x-slot:footnote>

    <x-email.notice
        :eyebrow="__('platform_events.mail.eyebrow')"
        :title="$model->kind->label()"
        :intro="$business === null ? $detail : $detail.' · '.$business"
        :chip="__('platform_events.mail.chip')"
        :primary-url="route('admin.alerts')"
        :primary-label="__('platform_events.mail.cta')"
        :closing="__('platform_events.mail.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
