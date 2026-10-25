@props(['question'])

{{-- "Preguntar" for the admin's AI: says THIS question to the panel (the client's statistics buttons, for the admin). The key is the lock; the button is only courtesy. --}}
@can('ai.ask')
    <button
        type="button"
        class="stats-ask"
        data-testid="ask-admin-chip"
        title="{{ __('ask_admin.title') }}"
        x-on:click="$dispatch('ask-admin', { question: @js($question) })"
    >
        <x-icon name="sparkles" :size="14" />
        {{ __('ask_admin.ask_button') }}
    </button>
@endcan
