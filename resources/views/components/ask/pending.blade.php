@props(['thinking'])

{{-- The question already asked and the three dots, drawn from the parent's Alpine `pending` while the answer is on its way. --}}
<template x-if="pending !== ''">
    <div class="ask-thread-pending">
        <div class="ask-msg ask-msg-owner" x-text="pending"></div>
        <div class="ask-msg ask-msg-ai ask-thinking">
            <span class="ask-dots"><i></i><i></i><i></i></span>
            {{ $thinking }}
        </div>
    </div>
</template>
