@props([
    'suggestions',       // Collection<KnowledgeSuggestion> just taught, each with its document
    'notified' => false, // the customers were already told this session
])

@php
    $single = $suggestions->count() === 1 ? $suggestions->first() : null;
    // Only a document that still exists can be waiting on its index; a deleted
    // one must not keep the banner polling forever.
    $learning = $suggestions->contains(fn ($suggestion) => $suggestion->document !== null && $suggestion->document->indexed_at === null);
    $customers = $notified ? 0 : $suggestions->sum(fn ($suggestion) => $suggestion->notifiableCustomers());
    $title = $single !== null
        ? __('client.assistant.taught_title', ['question' => $single->question])
        : trans_choice('client.assistant.taught_many_title', $suggestions->count(), ['count' => $suggestions->count()]);
@endphp

{{-- Right after teaching: the proof it works and the customers it can win
back. Polls only while an answer is still being indexed. --}}
<div class="mb-4" @if ($learning) wire:poll.3s @endif>
    <x-ui.alert variant="success" icon="check" :title="$title">
        <div class="mt-2 flex flex-wrap items-center gap-2">
            @if ($single !== null && $single->document?->indexed_at !== null)
                <x-ui.button
                    variant="secondary"
                    size="sm"
                    icon="bot"
                    class="data-loading:opacity-50"
                    wire:click="tryNow({{ $single->document->id }})"
                >
                    {{ __('client.assistant.try') }}
                </x-ui.button>
            @elseif ($learning)
                <span class="text-muted font-mono text-xs">{{ __('client.assistant.learning') }}</span>
            @endif

            @if ($customers > 0)
                <x-ui.button
                    variant="primary"
                    size="sm"
                    icon="send"
                    x-on:click="dialog.confirm({
                        title: {{ \Illuminate\Support\Js::from(trans_choice('client.assistant.notify_confirm_title', $customers, ['count' => $customers])) }},
                        message: {{ \Illuminate\Support\Js::from(__('client.assistant.notify_confirm_body')) }},
                        accept: {{ \Illuminate\Support\Js::from(__('client.assistant.notify_accept')) }},
                        type: 'info',
                    }).then((ok) => ok && $wire.notifyCustomers())"
                >
                    {{ trans_choice('client.assistant.notify_customers', $customers, ['count' => $customers]) }}
                </x-ui.button>
            @elseif ($notified)
                <span class="text-muted text-xs">{{ __('client.assistant.customers_notified') }}</span>
            @endif

            <x-ui.button variant="ghost" size="sm" class="ml-auto" wire:click="closeTaught">
                {{ __('client.assistant.taught_close') }}
            </x-ui.button>
        </div>
    </x-ui.alert>
</div>
