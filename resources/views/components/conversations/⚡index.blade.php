<?php

use App\Classes\Main\Client;
use App\Classes\Main\Inbox;
use App\Dto\NotificationDto;
use App\Enums\ConversationStatus;
use App\Enums\NotificationType;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Customer;
use App\Traits\HasNotifications;
use App\Traits\ManagesCustomerSheet;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
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
    use HasNotifications;
    use ManagesCustomerSheet;

    public ?int $selected = null;

    public string $search = '';

    /** ISO from the datepicker: one day ("Y-m-d") or a range ("Y-m-d..Y-m-d"). */
    public string $dates = '';

    /** Search WITHIN the open thread; it sweeps the whole history, not the window. */
    public string $threadSearch = '';

    /** The list walks in tranches; a filter change rewinds to the top. */
    public int $visible = 15;

    /** How many of the open thread's latest messages ride the render. */
    public int $window = 30;

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
        $threads = $this->withinDates($this->threads);
        $needle = Str::ascii(mb_strtolower(trim($this->search)));

        if ($needle === '') {
            return $threads;
        }

        $matches = $threads->filter(fn (Conversation $thread): bool => str_contains(Str::ascii(mb_strtolower((string) $thread->contact_name)), $needle)
            || str_contains($thread->contact_phone, $needle)
            || str_contains(Str::ascii(mb_strtolower((string) $thread->latestMessage?->body)), $needle));

        if ($matches->isNotEmpty() || mb_strlen($needle) < 4) {
            return $matches;
        }

        // Nothing literal: search by MEANING over what customers asked, so
        // "ecografías" finds the thread that said "eco doppler".
        $semantic = rescue(fn (): Collection => $this->inbox()?->searchThreads(trim($this->search)) ?? new Collection, new Collection, report: false);
        $semantic = $this->withinDates($semantic);
        $this->semanticUsed = $semantic->isNotEmpty();

        return $semantic;
    }

    /**
     * Keeps only the threads whose last exchange falls inside the picked
     * dates. Malformed input filters nothing: a filter must never take the
     * screen down over a hand-crafted wire payload.
     *
     * @param  Collection<int, Conversation>  $threads
     * @return Collection<int, Conversation>
     */
    private function withinDates(Collection $threads): Collection
    {
        $raw = trim($this->dates);

        if ($raw === '') {
            return $threads;
        }

        [$from, $to] = array_pad(explode('..', $raw, 2), 2, null);
        $to ??= $from;

        if (! Carbon::hasFormat((string) $from, 'Y-m-d') || ! Carbon::hasFormat((string) $to, 'Y-m-d')) {
            return $threads;
        }

        $start = Carbon::createFromFormat('Y-m-d', (string) $from)->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', (string) $to)->endOfDay();

        return $threads->filter(fn (Conversation $thread): bool => $thread->last_message_at !== null
            && $thread->last_message_at->between($start, $end));
    }

    /** @return Collection<int, Conversation> */
    #[Computed]
    public function rows(): Collection
    {
        return $this->filtered->take($this->visible)->values();
    }

    public function loadMore(): void
    {
        $this->visible += 15;
    }

    public function updatedSearch(): void
    {
        $this->visible = 15;
    }

    public function updatedDates(): void
    {
        $this->visible = 15;
    }

    #[Computed]
    public function thread(): ?Conversation
    {
        return $this->selected === null ? null : $this->inbox()?->thread($this->selected, $this->window);
    }

    #[Computed]
    public function hasOlder(): bool
    {
        return ($this->thread?->messages_count ?? 0) > $this->window;
    }

    /** Scoped to the thread island: only the canvas re-renders per tranche. */
    public function loadOlder(): void
    {
        $this->window += 30;
    }

    /**
     * The open thread bucketed by calendar day, so the canvas can drop a
     * WhatsApp-style date chip between days. Searching swaps the window for
     * the matches across the WHOLE history — old quotes are the point.
     *
     * @return Collection<string, Collection<int, ConversationMessage>>
     */
    #[Computed]
    public function threadDays(): Collection
    {
        $needle = trim($this->threadSearch);

        $messages = $needle === ''
            ? $this->thread?->messages
            : $this->inbox()?->threadMatches((int) $this->selected, $needle);

        return $messages
            ?->groupBy(fn (ConversationMessage $message): string => (string) $message->created_at?->toDateString())
            ?? new Collection;
    }

    public function open(int $id): void
    {
        $this->selected = $id;
        $this->window = 30;
        $this->threadSearch = '';
        $this->showCustomer = false;
    }

    public function close(): void
    {
        $this->selected = null;
        $this->showCustomer = false;
    }

    /** The sheet looks at whoever owns the open thread. */
    protected function sheetCustomer(): ?Customer
    {
        return $this->thread?->customer;
    }

    /** The return leg of the handoff: the owner hands the thread back. */
    public function resume(): void
    {
        $thread = $this->thread;

        if ($thread === null || $thread->status !== ConversationStatus::Team) {
            return;
        }

        $thread->update(['status' => ConversationStatus::Open]);
        unset($this->thread);

        $this->dispatchNotification(new NotificationDto(__('client.conversations.resumed'), NotificationType::Success));
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
                {{-- A declared row: the search absorbs the slack (golden rule,
                formularios §5); in this narrow pane the dates wrap under it. --}}
                <div class="bd-subtle border-b p-3">
                    <x-catalog.form-row>
                        <x-inputsform.input
                            span="text"
                            name="search"
                            icon="search"
                            :placeholder="__('client.conversations.search')"
                            :aria-label="__('client.conversations.search')"
                            wire:model.live.debounce.300ms="search"
                        />
                        <x-inputsform.datepicker
                            span="short"
                            name="dates"
                            mode="range"
                            :placeholder="__('client.conversations.dates')"
                            wire:model.live="dates"
                        />
                    </x-catalog.form-row>
                </div>

                {{-- Computed FIRST: rendering it is what raises the semantic flag. --}}
                @php($rows = $this->rows)

                @if ($this->semanticUsed)
                    <p class="bg-brand-soft text-muted px-4 py-1.5 text-xs">{{ __('client.conversations.semantic') }}</p>
                @endif

                @if ($rows->isEmpty() && (trim($search) !== '' || trim($dates) !== ''))
                    <p class="text-muted px-4 py-6 text-center text-sm">{{ __('client.conversations.no_results') }}</p>
                @endif

                <div class="max-h-[65vh] overflow-y-auto">
                <ul>
                    @foreach ($rows as $row)
                        <li wire:key="row-{{ $row->id }}">
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
                                            <x-ui.match :text="$row->contact_name ?? __('client.conversations.anonymous')" :needle="$search" />
                                        </span>
                                        @if ($row->status === ConversationStatus::Team)
                                            <x-ui.badge variant="accent">{{ __('client.conversations.status_team_chip') }}</x-ui.badge>
                                        @endif
                                        <span class="text-subtle flex-none font-mono text-xs">
                                            {{ $row->last_message_at?->isToday() ? $row->last_message_at->format('H:i') : $row->last_message_at?->format('d/m') }}
                                        </span>
                                    </span>
                                    <span class="text-muted block truncate text-xs">
                                        @if ($row->latestMessage?->direction === App\Enums\MessageDirection::Out)
                                            {{ __('client.conversations.assistant_prefix') }}
                                        @endif
                                        <x-ui.match :text="$row->latestMessage?->body ?? ''" :needle="$search" />
                                    </span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <x-ui.load-more class="pb-3" :shown="$rows->count()" :total="$this->filtered->count()" />
                </div>
            </x-ui.card>

            <x-ui.card class="{{ $selected === null ? 'hidden lg:flex' : 'flex' }} flex-col overflow-hidden p-0">
                @if ($this->thread !== null)
                    <div class="bd-subtle flex items-center gap-3 border-b p-3">
                        <span class="lg:hidden">
                            <x-ui.icon-button icon="chevron-left" size="sm" variant="ghost" :label="__('client.conversations.back')" wire:click="close" />
                        </span>
                        <x-ui.avatar :name="$this->thread->contact_name ?? $this->thread->contact_phone" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="text-strong truncate text-sm font-semibold">
                                {{ $this->customer?->displayName() ?? $this->thread->contact_name ?? __('client.conversations.anonymous') }}
                            </p>
                            <p class="text-muted font-mono text-xs">{{ $this->thread->contact_phone }}</p>
                        </div>
                        @if ($this->thread->status === ConversationStatus::Team)
                            <span class="status-tag is-warning">
                                <span class="dot"></span>
                                {{ __('client.conversations.status_team') }}
                            </span>
                            <x-ui.button variant="primary" size="sm" icon="bot" wire:click="resume">
                                {{ __('client.conversations.resume') }}
                            </x-ui.button>
                        @endif
                        @if ($this->customer !== null)
                            <x-ui.button variant="secondary" size="sm" icon="user" wire:click="openCustomer">
                                {{ __('client.customers.open') }}
                            </x-ui.button>
                        @endif
                    </div>

                    <div class="bd-subtle border-b px-3 py-2">
                        <x-catalog.form-row>
                            <x-inputsform.input
                                span="full"
                                size="s"
                                name="thread_search"
                                icon="search"
                                :placeholder="__('client.conversations.search_thread')"
                                :aria-label="__('client.conversations.search_thread')"
                                wire:model.live.debounce.300ms="threadSearch"
                            />
                        </x-catalog.form-row>
                    </div>
                @endif

                {{-- The canvas is an island: walking back in time re-renders
                ONLY this region, never the list nor the toolbar. `always`
                because opening a thread happens outside the island, and the
                island guards its own emptiness (an island cannot sit in an
                @if). --}}
                @island(name: 'thread', always: true)
                        @if ($this->thread !== null)
                            <div
                                wire:key="canvas-{{ $selected }}"
                                class="max-h-[58vh] flex-1 space-y-2 overflow-y-auto p-4"
                                style="background: var(--chat-canvas)"
                                x-data
                                x-init="$el.scrollTop = $el.scrollHeight"
                            >
                                {{-- A search already sweeps the whole history:
                                walking back would be a second, confusing door. --}}
                                @if ($this->hasOlder && trim($threadSearch) === '')
                                    <div class="flex justify-center">
                                        <x-ui.button variant="secondary" size="sm" class="data-loading:opacity-50" wire:click="loadOlder">
                                            {{ __('client.conversations.older') }}
                                        </x-ui.button>
                                    </div>
                                @endif

                                @if ($this->threadDays->isEmpty() && trim($threadSearch) !== '')
                                    <p class="text-muted py-6 text-center text-sm">{{ __('client.conversations.thread_no_results') }}</p>
                                @endif

                                {{-- One wrapper per day: the sticky chip is boxed by its
                                group, so it unsticks when the day scrolls past instead
                                of piling on the next chip. --}}
                                @foreach ($this->threadDays as $messages)
                                    @php($day = $messages->first()->created_at)
                                    <div class="space-y-2" wire:key="day-{{ $day->toDateString() }}">
                                        <div class="pm-day">
                                            <span>
                                                {{ $day->isToday() ? __('client.conversations.day_today') : ($day->isYesterday() ? __('client.conversations.day_yesterday') : $day->format('d/m/Y')) }}
                                            </span>
                                        </div>
                                        @foreach ($messages as $message)
                                            <div class="pm-row {{ $message->direction->value }}" wire:key="msg-{{ $message->id }}">
                                                <div class="pm-bubble {{ $message->direction->value }}">
                                                    <x-ui.match :text="$message->body" :needle="$threadSearch" />
                                                    <span class="pm-time font-mono">{{ $message->created_at?->format('H:i') }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                @endisland

                @if ($this->thread !== null)
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

        <x-client.customer-sheet
            :customer="$this->customer"
            :duplicate="$this->customerDuplicate"
            :form="$customerForm"
            :show="$showCustomer"
        />
    @endif
</div>
