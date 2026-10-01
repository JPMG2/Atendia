@props(['term'])

{{-- A question belongs to the assistant, not to a list of names. The palette
hands it over through the window event ask-atendia already listens for. --}}
<button
    type="button"
    class="cmdk-row cmdk-row-ask"
    data-row
    data-testid="cmdk-ask"
    x-on:click="hide(); $dispatch('ask-atendia', { question: @js($term) })"
    :class="rows()[cursor] === $el && 'is-cursor'"
    x-on:mouseenter="cursor = rows().indexOf($el)"
>
    <span class="cmdk-row-icon"><x-icon name="sparkles" :size="16" /></span>
    <span class="cmdk-row-text">
        <span class="cmdk-row-title">{{ __('search.ask.title', ['term' => $term]) }}</span>
        <span class="cmdk-row-sub">{{ __('search.ask.sub') }}</span>
    </span>
</button>
