<x-errors.layout
    code="429"
    :title="__('errors.too_many_requests.title')"
    :message="__('errors.too_many_requests.message')"
>
    <x-ui.button :href="url('/')" variant="primary" icon="chevron-left">{{ __('errors.back_home') }}</x-ui.button>
</x-errors.layout>
