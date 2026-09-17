<?php

use App\Classes\Main\Client;
use App\Classes\Main\Inbox;
use App\Models\Conversation;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Conversaciones" — the owner reads every thread the assistant holds.
 * Read-only on purpose: replying is the human-handoff phase. Two panes on
 * desktop, list-then-thread on mobile; new exchanges arrive live over the
 * per-business Reverb channel.
 */
new class extends Component
{
    public ?int $selected = null;

    public string $search = '';

    /** Set while computing the list: tells the view the meaning search kicked in. */
    public bool $semanticUsed = false;

    /**
     * The inbox refreshes the moment the worker broadcasts a new exchange —
     * same private per-business channel the whole panel uses.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $businessId = (int) Auth::user()?->business_id;

        return ["echo-private:business.{$businessId},.whatsapp.exchange" => '$refresh'];
    }

    private function inbox(): ?Inbox
    {
        return Client::for(Auth::user())->inbox;
    }

    #[Computed]
    public function todayCount(): int
    {
        return $this->inbox()?->todayCount ?? 0;
    }

    /** @return Collection<int, Conversation> */
    #[Computed]
    public function threads(): Collection
    {
        return $this->inbox()?->threads ?? new Collection;
    }

    /**
     * Accent-free search over the loaded list: filtering in memory is
     * presentation, and an inbox is small by nature.
     *
     * @return Collection<int, Conversation>
     */
    #[Computed]
    public function filtered(): Collection
    {
        $this->semanticUsed = false;
        $needle = Str::ascii(mb_strtolower(trim($this->search)));

        if ($needle === '') {
            return $this->threads;
        }

        $matches = $this->threads->filter(fn (Conversation $thread): bool => str_contains(Str::ascii(mb_strtolower((string) $thread->contact_name)), $needle)
            || str_contains($thread->contact_phone, $needle));

        if ($matches->isNotEmpty() || mb_strlen($needle) < 4) {
            return $matches;
        }

        // Nothing literal: search by MEANING over what customers asked, so
        // "ecografías" finds the thread that said "eco doppler".
        $semantic = rescue(fn (): Collection => $this->inbox()?->searchThreads(trim($this->search)) ?? new Collection, new Collection, report: false);
        $this->semanticUsed = $semantic->isNotEmpty();

        return $semantic;
    }

    #[Computed]
    public function thread(): ?Conversation
    {
        return $this->selected === null ? null : $this->inbox()?->thread($this->selected);
    }

    public function open(int $id): void
    {
        $this->selected = $id;
    }

    public function close(): void
    {
        $this->selected = null;
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('client.conversations.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.conversations.title') }}</h1>
            <p class="page-head-sub">{{ __('client.conversations.sub') }}</p>
        </div>
        @if ($this->todayCount > 0)
            <span class="status-tag is-brand">
                <span class="dot"></span>
                {{ __('client.conversations.today', ['count' => $this->todayCount]) }}
            </span>
        @endif
    </div>

    @if ($this->threads->isEmpty())
        <x-ui.card class="p-6">
            <div class="flex items-start gap-4">
                <div class="bg-brand-soft flex size-11 flex-none items-center justify-center rounded-xl" style="color: var(--brand)">
                    <x-icon name="message-circle" :size="22" />
                </div>
                <div class="min-w-0">
                    <h2 class="font-display text-strong text-base">{{ __('client.conversations.empty_title') }}</h2>
                    <p class="text-body mt-1 text-sm">{{ __('client.conversations.empty_body') }}</p>
                </div>
            </div>
        </x-ui.card>
    @else
        <div class="grid items-start gap-4 lg:grid-cols-[340px_1fr]">
            <x-ui.card class="{{ $selected !== null ? 'hidden lg:block' : '' }} overflow-hidden p-0">
                <div class="bd-subtle border-b p-3">
                    <x-ui.input
                        name="search"
                        icon="search"
                        :placeholder="__('client.conversations.search')"
                        wire:model.live.debounce.300ms="search"
                    />
                </div>

                {{-- Computed FIRST: rendering it is what raises the semantic flag. --}}
                @php($rows = $this->filtered)

                @if ($this->semanticUsed)
                    <p class="bg-brand-soft text-muted px-4 py-1.5 text-xs">{{ __('client.conversations.semantic') }}</p>
                @endif

                <ul class="max-h-[65vh] overflow-y-auto">
                    @foreach ($rows as $row)
                        <li>
                            <button
                                type="button"
                                wire:click="open({{ $row->id }})"
                                @class([
                                    'flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors',
                                    'hover:bg-sunken' => $selected !== $row->id,
                                    'bg-brand-soft' => $selected === $row->id,
                                ])
                            >
                                <x-ui.avatar :name="$row->contact_name ?? $row->contact_phone" size="sm" />
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="text-strong truncate text-sm font-semibold">
                                            {{ $row->contact_name ?? __('client.conversations.anonymous') }}
                                        </span>
                                        <span class="text-subtle flex-none font-mono text-xs">
                                            {{ $row->last_message_at?->isToday() ? $row->last_message_at->format('H:i') : $row->last_message_at?->format('d/m') }}
                                        </span>
                                    </span>
                                    <span class="text-muted block truncate text-xs">
                                        @if ($row->latestMessage?->direction === App\Enums\MessageDirection::Out)
                                            {{ __('client.conversations.assistant_prefix') }}
                                        @endif
                                        {{ $row->latestMessage?->body }}
                                    </span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            <x-ui.card class="{{ $selected === null ? 'hidden lg:flex' : 'flex' }} flex-col overflow-hidden p-0">
                @if ($this->thread !== null)
                    <div class="bd-subtle flex items-center gap-3 border-b p-3">
                        <span class="lg:hidden">
                            <x-ui.icon-button icon="chevron-left" size="sm" variant="ghost" :label="__('client.conversations.back')" wire:click="close" />
                        </span>
                        <x-ui.avatar :name="$this->thread->contact_name ?? $this->thread->contact_phone" size="sm" />
                        <div class="min-w-0">
                            <p class="text-strong truncate text-sm font-semibold">
                                {{ $this->thread->contact_name ?? __('client.conversations.anonymous') }}
                            </p>
                            <p class="text-muted font-mono text-xs">{{ $this->thread->contact_phone }}</p>
                        </div>
                    </div>

                    <div class="max-h-[58vh] flex-1 space-y-2 overflow-y-auto p-4" style="background: var(--chat-canvas)">
                        @foreach ($this->thread->messages as $message)
                            <div class="pm-row {{ $message->direction->value }}">
                                <div class="pm-bubble {{ $message->direction->value }}">
                                    {{ $message->body }}
                                    <span class="pm-time font-mono">{{ $message->created_at?->format('H:i') }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <p class="bd-subtle text-muted flex items-center gap-2 border-t p-3 text-xs">
                        <x-icon name="lock" :size="14" />
                        {{ __('client.conversations.read_only') }}
                    </p>
                @else
                    <div class="grid min-h-[30vh] flex-1 place-items-center p-8">
                        <p class="text-muted text-sm">{{ __('client.conversations.select') }}</p>
                    </div>
                @endif
            </x-ui.card>
        </div>
    @endif
</div>
