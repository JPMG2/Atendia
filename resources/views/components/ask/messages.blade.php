@props(['messages', 'rateable' => false])

{{-- The thread of both assistants (client and admin): one bubble per turn, the owner's plain and the AI's with its own links live.
Rating is the client's alone: it feeds a table that belongs to a business. --}}
@foreach ($messages as $index => $message)
    {{-- A decided e-mail draft leaves a settled line, not a bubble. --}}
    @if ($message['role'] === 'note')
        <div class="ask-note" wire:key="ask-message-{{ $index }}">
            <x-icon name="mail" :size="14" /> {{ $message['text'] }}
        </div>
        @continue
    @endif
    <div
        wire:key="ask-message-{{ $index }}"
        @class(['ask-msg', 'ask-msg-owner' => $message['role'] === 'owner', 'ask-msg-ai' => $message['role'] !== 'owner'])
    >
        @if ($message['role'] === 'owner')
            {{ $message['text'] }}
        @else
            {!! \App\Classes\Main\AnswerHtml::of($message['text']) !!}
        @endif
    </div>
    @if ($rateable && $message['role'] === 'ai' && array_key_exists('rating', $message))
        <div class="ask-rate" wire:key="ask-rate-{{ $index }}">
            @if ($message['rating'] === null)
                <x-ui.icon-button
                    icon="thumbs-up"
                    size="sm"
                    variant="ghost"
                    :label="__('ask.rate.up')"
                    :title="__('ask.rate.up')"
                    wire:click="rate({{ $index }}, 'up')"
                />
                <x-ui.icon-button
                    icon="thumbs-down"
                    size="sm"
                    variant="ghost"
                    :label="__('ask.rate.down')"
                    :title="__('ask.rate.down')"
                    wire:click="rate({{ $index }}, 'down')"
                />
            @else
                <span class="ask-rate-done">
                    <x-icon :name="$message['rating'] === 'up' ? 'thumbs-up' : 'thumbs-down'" :size="14" />
                    {{ __('ask.rate.thanks') }}
                </span>
            @endif
        </div>
    @endif
@endforeach
