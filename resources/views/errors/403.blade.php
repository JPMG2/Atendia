<x-errors.layout code="403" :title="__('errors.forbidden.title')" :message="__('errors.forbidden.message')">
    <x-ui.button :href="url('/')" variant="primary" icon="chevron-left">{{ __('errors.back_home') }}</x-ui.button>
</x-errors.layout>
