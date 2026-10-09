<x-email.layout :preheader="__('mail.staff_two_factor_deadline.preheader')">
    <x-slot:footnote>{{ __('mail.staff_two_factor_deadline.reason') }}</x-slot:footnote>

    <x-email.notice
        glyph="&#9888;"
        :eyebrow="__('mail.account.two_factor_eyebrow')"
        :title="$deadline->isTomorrow() ? __('mail.staff_two_factor_deadline.title') : __('mail.staff_two_factor_deadline.title_soon', ['date' => $deadline->format('d/m/Y')])"
        :intro="__('mail.staff_two_factor_deadline.intro', ['name' => $model->name, 'date' => $deadline->format('d/m/Y')])"
        :body="__('mail.staff_two_factor_deadline.body')"
        :alert="__('mail.staff_two_factor_deadline.alert')"
        :primary-url="route('admin.security')"
        :primary-label="__('mail.staff_two_factor_deadline.cta')"
        :closing="__('mail.staff_two_factor_deadline.closing')"
        :team="__('mail.account.team')"
    />
</x-email.layout>
