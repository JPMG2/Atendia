<x-errors.layout code="500" :title="__('errors.server_error.title')" :message="__('errors.server_error.message')">
    <x-ui.button :href="url('/')" variant="primary" icon="chevron-left">{{ __('errors.back_home') }}</x-ui.button>
</x-errors.layout>
