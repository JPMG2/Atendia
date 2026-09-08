<x-errors.layout code="404" :title="__('errors.not_found.title')" :message="__('errors.not_found.message')">
    <x-ui.button :href="url('/')" variant="primary" icon="chevron-left">{{ __('errors.back_home') }}</x-ui.button>
</x-errors.layout>
