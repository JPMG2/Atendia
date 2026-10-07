<x-email.layout :preheader="__('platform.new_business.preheader')">
    <x-email.notice
        :eyebrow="__('platform.new_business.eyebrow')"
        :title="__('platform.new_business.subject', ['business' => $business->name])"
        :intro="__('platform.new_business.intro')"
        :body="__('platform.new_business.line', [
            'business' => $business->name,
            'email' => $business->billing_email,
            'country' => $business->country?->name ?? __('platform.new_business.no_country'),
        ])"
        :primary-url="route('admin.businesses', ['negocio' => $business->id])"
        :primary-label="__('platform.new_business.cta')"
        :closing="__('platform.new_business.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
