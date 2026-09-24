@props([
    'suggestion',        // KnowledgeSuggestion just taught, with its document
    'notified' => false, // the customers were already told this session
])

@php
    $indexed = $suggestion->document?->indexed_at !== null;
    $customers = $notified ? 0 : $suggestion->notifiableCustomers();
@endphp

{{-- Right after teaching: the proof it works and the customers it can win
back. Polls only while the answer is still being indexed. --}}
<div class="mb-4" @unless ($indexed) wire:poll.3s @endunless>
    <x-ui.alert
        variant="success"
        icon="check"
        :title="__('client.assistant.taught_title', ['question' => $suggestion->question])"
    >
        <div class="mt-2 flex flex-wrap items-center gap-2">
            @if ($indexed)
                <x-ui.button
                    variant="secondary"
                    size="sm"
                    icon="bot"
                    class="data-loading:opacity-50"
                    wire:click="tryNow({{ $suggestion->document->id }})"
                >
                    {{ __('client.assistant.try') }}
                </x-ui.button>
            @else
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
