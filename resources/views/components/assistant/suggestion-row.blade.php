@props([
    'suggestion',     // KnowledgeSuggestion from the queue scope
    'draft' => null,  // the team's answer or the AI draft, null when there is none
])

{{-- One "your assistant did not know this" item; its buttons call the
assistant screen's Livewire actions. --}}
<li {{ $attributes->merge(['class' => 'hover:bg-sunken flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2.5 transition-colors']) }}>
    <span class="min-w-0 flex-1">
        <span class="text-strong block text-sm font-semibold">{{ $suggestion->question }}</span>
        @if ($suggestion->knowledge_document_id !== null)
            {{-- Taught before and failed again: the lesson did not stick. --}}
            <span class="mt-0.5 flex items-center gap-1.5 text-xs" style="color: var(--warning)">
                <x-icon name="rotate-ccw" :size="14" class="flex-none" />
                {{ __('client.assistant.suggestion_relapsed') }}
            </span>
        @endif
        @if ($draft !== null)
            <span class="text-muted mt-0.5 flex items-start gap-1.5 text-xs">
                <x-icon
                    :name="$suggestion->teamAnswer !== null ? 'users' : 'bot'"
                    :size="14"
                    style="color: var(--brand)"
                    class="mt-0.5 flex-none"
                />
                <span>
                    <span class="font-semibold">{{ $suggestion->teamAnswer !== null ? __('client.assistant.draft_from_team') : __('client.assistant.draft_from_ai') }}</span>
                    {{ $draft }}
                </span>
            </span>
        @else
            <span class="text-subtle mt-0.5 block text-xs">{{ __('client.assistant.no_answer_yet') }}</span>
        @endif
    </span>
    <span class="text-subtle flex-none font-mono text-xs">
        {{ trans_choice('client.assistant.miss_count', $suggestion->asked_count, ['count' => $suggestion->asked_count]) }}
    </span>
    <span class="flex flex-none items-center gap-1">
        @if ($suggestion->latestQuestion !== null)
            {{-- The context door: read how it was asked before teaching the answer. --}}
            <x-ui.button
                variant="ghost"
                size="sm"
                icon="message-circle"
                :href="route('conversations', ['hilo' => $suggestion->latestQuestion->conversation_id])"
                wire:navigate
            >
                {{ __('client.assistant.view_thread') }}
            </x-ui.button>
        @endif
        <x-ui.icon-button
            icon="x"
            size="sm"
            variant="ghost"
            :label="__('client.assistant.dismiss')"
            x-on:click="dialog.confirm({
                title: {{ \Illuminate\Support\Js::from(__('client.assistant.dismiss_confirm_title')) }},
                message: {{ \Illuminate\Support\Js::from(__('client.assistant.dismiss_confirm_body')) }},
                accept: {{ \Illuminate\Support\Js::from(__('client.assistant.dismiss')) }},
                type: 'warning',
            }).then((ok) => ok && $wire.dismissSuggestion({{ $suggestion->id }}))"
        />
        @if ($draft !== null)
            <x-ui.button
                variant="secondary"
                size="sm"
                icon="pencil"
                wire:click="teachSuggestion({{ $suggestion->id }})"
            >
                {{ __('client.assistant.edit_draft') }}
            </x-ui.button>
            <x-ui.button variant="primary" size="sm" icon="check" wire:click="approve({{ $suggestion->id }})">
                {{ __('client.assistant.approve') }}
            </x-ui.button>
        @else
            <x-ui.button
                variant="secondary"
                size="sm"
                icon="sparkles"
                wire:click="teachSuggestion({{ $suggestion->id }})"
            >
                {{ __('client.assistant.teach') }}
            </x-ui.button>
        @endif
    </span>
</li>
