<x-errors.layout code="419" :title="__('errors.session_expired.title')" :message="__('errors.session_expired.message')">
    <x-ui.button :href="route('login')" variant="primary" icon="lock">{{ __('errors.login_again') }}</x-ui.button>
</x-errors.layout>
