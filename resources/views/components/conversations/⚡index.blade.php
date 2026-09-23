<?php

use App\Actions\Business\SaveInternalNote;
use App\Actions\Business\SendHumanReply;
use App\Classes\Main\Client;
use App\Classes\Main\Inbox;
use App\Dto\NotificationDto;
use App\Enums\ConversationStatus;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\AssistantFaqForm;
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
use Livewire\Attributes\Url;
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

    /** In the URL so "Ver el hilo" can land here with the thread open. */
    #[Url(as: 'hilo', except: null)]
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

    public AssistantFaqForm $form;

    public bool $sheetOpen = false;

    /** From the thread: the customer's own words become the taught question. */
    public function teach(int $messageId): void
    {
        $message = $this->selected === null ? null : $this->inbox()?->customerMessage($this->selected, $messageId);

        if ($message === null) {
            return;
        }

        $this->form->setup();
        $this->form->question = mb_substr(trim($message->body), 0, 200);
        $this->form->conversationId = $this->selected;
        $this->sheetOpen = true;
    }

    /**
     * A source names its document: a taught answer opens right here for an
     * instant correction; an automatic feed jumps to the screen that owns it.
     */
    public function openSource(int $documentId): void
    {
        $document = Client::for(Auth::user())->knowledgeBase?->document($documentId);

        if ($document === null) {
            return;
        }

        if ($document->source_type === 'faq') {
            $this->form->setup($document);
            $this->sheetOpen = true;

            return;
        }

        $this->redirect(route(match ($document->source_type) {
            'profile' => 'my-business',
            'services' => 'my-services',
            'products' => 'my-products',
            default => 'assistant',
        }), navigate: true);
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function saveFaq(): void
    {
        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Error) {
            $this->sheetOpen = false;
        }
    }

    /** The sheet looks at whoever owns the open thread. */
    protected function sheetCustomer(): ?Customer
    {
        return $this->thread?->customer;
    }

    /** True while a human holds the thread, whichever side has the ball. */
    #[Computed]
    public function humanHeld(): bool
    {
        return in_array($this->thread?->status, [ConversationStatus::Team, ConversationStatus::Customer], true);
    }

    /** The return leg of the handoff: the owner hands the thread back. */
    public function resume(): void
    {
        if (! $this->humanHeld) {
            return;
        }

        $this->thread?->update(['status' => ConversationStatus::Open, 'escalated_at' => null, 'handoff_reminded_at' => null]);
        unset($this->thread, $this->humanHeld);

        $this->dispatchNotification(new NotificationDto(__('client.conversations.resumed'), NotificationType::Success));
    }

    /** The owner takes the thread by hand: same silence as an AI escalation. */
    public function takeover(): void
    {
        $thread = $this->thread;

        if ($thread === null || $thread->status !== ConversationStatus::Open) {
            return;
        }

        $thread->update(['status' => ConversationStatus::Team, 'escalated_at' => now(), 'handoff_reminded_at' => null]);
        unset($this->thread, $this->humanHeld);
    }

    public string $reply = '';

    /** The composer: out through the business's WhatsApp, into the record. */
    public function sendReply(): void
    {
        $thread = $this->thread;
        $business = Auth::user()?->business;
        $text = trim($this->reply);

        if ($thread === null || $business === null || $text === '' || ! $this->humanHeld) {
            return;
        }

        $sent = app(SendHumanReply::class)->handle($business, $thread, $text);

        if ($sent === null) {
            $this->dispatchNotification(new NotificationDto(__('client.customers.opt_in_unavailable'), NotificationType::Error));

            return;
        }

        $this->reply = '';
        unset($this->thread, $this->threadDays, $this->humanHeld);
    }

    /**
     * One-tap answers built from what the owner already loaded: hours,
     * address, the top of the menu. They drop INTO the box — the human
     * always edits and sends; nothing fires on its own.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function quickReplies(): array
    {
        $user = Auth::user();
        $business = $user?->business;

        if ($business === null) {
            return [];
        }

        $services = Client::for($user)->serviceMenu?->services
            ->where('is_active', true)
            ->take(3)
            ->map(fn ($service): string => $service->price !== null
                ? $service->name.': $ '.rtrim(rtrim((string) $service->price, '0'), '.')
                : $service->name)
            ->implode("\n") ?? '';

        return array_filter([
            __('client.conversations.qr_hours') => implode("\n", $business->scheduleLines()),
            __('client.conversations.qr_address') => trim(implode(', ', array_filter([$business->address, $business->city]))),
            __('client.conversations.qr_services') => $services,
        ], fn (string $text): bool => $text !== '');
    }

    public function quickReply(string $text): void
    {
        $this->reply = trim($this->reply) === '' ? $text : rtrim($this->reply)."\n".$text;
    }

    /** The same box, kept private: a margin note the customer never sees. */
    public function saveNote(): void
    {
        $thread = $this->thread;
        $text = trim($this->reply);

        if ($thread === null || $text === '' || ! $this->humanHeld) {
            return;
        }

        app(SaveInternalNote::class)->handle($thread, $text);

        $this->reply = '';
        unset($this->thread, $this->threadDays);
    }

    /** Closing the loop by hand; the customer's next word reopens it for the AI. */
    public function markResolved(): void
    {
        if (! $this->humanHeld) {
            return;
        }

        $this->thread?->update(['status' => ConversationStatus::Resolved, 'escalated_at' => null, 'handoff_reminded_at' => null]);
        unset($this->thread, $this->humanHeld);

        $this->dispatchNotification(new NotificationDto(__('client.conversations.resolved_done'), NotificationType::Success));
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
                        @if (($this->thread->taught_faqs_count ?? 0) > 0)
                            {{-- Reverse provenance: this chat made the assistant smarter.
                            Clicking unfolds WHICH answers, each a jump to its sheet. --}}
                            <div class="relative" x-data="{ open: false }" x-on:click.outside="open = false">
                                <button type="button" class="badge badge-brand cursor-pointer transition-[filter] hover:brightness-95" x-on:click="open = !open">
                                    <x-icon name="graduation-cap" :size="12" />
                                    {{ trans_choice('client.conversations.taught_badge', $this->thread->taught_faqs_count, ['count' => $this->thread->taught_faqs_count]) }}
                                </button>
                                <div x-show="open" x-cloak class="taught-popover bg-card bd-subtle absolute right-0 top-full z-20 mt-2 w-64 max-w-[80vw] rounded-xl border p-2 shadow-lg">
                                    <p class="text-subtle px-2 pb-1 text-xs">{{ __('client.conversations.taught_list_title') }}</p>
                                    <ul>
                                        @foreach ($this->thread->taughtFaqs as $taught)
                                            <li wire:key="taught-{{ $taught->id }}">
                                                <button
                                                    type="button"
                                                    class="hover:bg-sunken text-strong flex w-full items-start gap-2 rounded-lg px-2 py-1.5 text-left text-sm transition-colors"
                                                    x-on:click="open = false"
                                                    wire:click="openSource({{ $taught->id }})"
                                                >
                                                    <x-icon name="book-open" :size="14" class="mt-0.5 flex-none" style="color: var(--brand)" />
                                                    <span class="min-w-0">{{ $taught->title }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        @endif
                        @if ($this->thread->status === ConversationStatus::Team)
                            <span class="status-tag is-warning">
                                <span class="dot"></span>
                                {{ __('client.conversations.status_team') }}
                            </span>
                        @elseif ($this->thread->status === ConversationStatus::Customer)
                            <span class="status-tag is-info">
                                <span class="dot"></span>
                                {{ __('client.conversations.status_customer') }}
                            </span>
                        @elseif ($this->thread->status === ConversationStatus::Resolved)
                            <span class="status-tag is-success">
                                <span class="dot"></span>
                                {{ __('client.conversations.status_resolved') }}
                            </span>
                        @endif
                        @if ($this->humanHeld)
                            <x-ui.button variant="secondary" size="sm" icon="check" wire:click="markResolved">
                                {{ __('client.conversations.mark_resolved') }}
                            </x-ui.button>
                            <x-ui.button variant="primary" size="sm" icon="bot" wire:click="resume">
                                {{ __('client.conversations.resume') }}
                            </x-ui.button>
                        @elseif ($this->thread->status === ConversationStatus::Open)
                            <x-ui.button variant="secondary" size="sm" icon="user" wire:click="takeover">
                                {{ __('client.conversations.takeover') }}
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
                                            @if ($message->kind === App\Enums\MessageKind::Note)
                                                <div class="pm-note" wire:key="msg-{{ $message->id }}">
                                                    <x-icon name="lock" :size="12" class="mt-1 flex-none" />
                                                    <span class="min-w-0">{{ $message->body }}</span>
                                                    <span class="pm-time font-mono">{{ $message->created_at?->format('H:i') }}</span>
                                                </div>
                                                @continue
                                            @endif
                                            <div class="pm-row {{ $message->direction->value }} group" wire:key="msg-{{ $message->id }}">
                                                <div class="pm-bubble {{ $message->direction->value }}">
                                                    <x-ui.match :text="$message->body" :needle="$threadSearch" />
                                                    @if ($message->author === App\Enums\MessageAuthor::Assistant && ($message->knowledge_sources ?? []) !== [])
                                                        <div x-data="{ open: false }" class="mt-1">
                                                            <button type="button" class="text-[11px] underline decoration-dotted opacity-70 transition-opacity hover:opacity-100" x-on:click="open = !open">
                                                                {{ __('client.conversations.sources_toggle') }}
                                                            </button>
                                                            <ul x-show="open" x-cloak class="mt-1 space-y-0.5 text-[11px] opacity-80">
                                                                @foreach ($message->knowledge_sources as $source)
                                                                    <li wire:key="src-{{ $message->id }}-{{ $source['id'] }}">
                                                                        <button
                                                                            type="button"
                                                                            class="flex items-start gap-1.5 text-left underline-offset-2 transition-opacity hover:underline hover:opacity-100"
                                                                            title="{{ __('client.conversations.source_open') }}"
                                                                            wire:click="openSource({{ $source['id'] }})"
                                                                        >
                                                                            <x-icon name="book-open" :size="12" class="mt-0.5 flex-none" />
                                                                            <span class="min-w-0">{{ $source['title'] }}</span>
                                                                        </button>
                                                                    </li>
                                                                @endforeach
                                                            </ul>
                                                        </div>
                                                    @endif
                                                    <span class="pm-time font-mono">
                                                        @if ($message->author === App\Enums\MessageAuthor::Human)
                                                            {{ __('client.conversations.human_tag') }} ·
                                                        @endif
                                                        {{ $message->created_at?->format('H:i') }}
                                                    </span>
                                                </div>
                                                @if ($message->is_enquiry && $message->needs_teaching)
                                                    {{-- Only where the AI triage saw the assistant fall short: that is what teaching fixes.
                                                    Hover-revealed on desktop; touch has no hover, so it stays faintly visible. --}}
                                                    <x-ui.icon-button
                                                        icon="graduation-cap"
                                                        size="sm"
                                                        variant="ghost"
                                                        class="ml-1 self-center opacity-60 transition-opacity lg:opacity-0 lg:group-hover:opacity-100 lg:focus-visible:opacity-100"
                                                        :label="__('client.conversations.teach')"
                                                        wire:click="teach({{ $message->id }})"
                                                    />
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>

                            {{-- Inside the island on purpose: "teach" fires from island
                            context, and an island-scoped render never repaints
                            markup that lives outside it. --}}
                            <x-client.faq-sheet :form="$form" :show="$sheetOpen" />
                        @endif
                @endisland

                @if ($this->thread !== null)
                    @if ($this->humanHeld)
                        {{-- The composer: out through the business's own
                        WhatsApp, into the record and the AI's memory. --}}
                        <div class="bd-subtle border-t p-3">
                            @if ($this->quickReplies !== [])
                                <div class="mb-2 flex flex-wrap gap-2">
                                    @foreach ($this->quickReplies as $label => $text)
                                        <x-ui.button variant="ghost" size="sm" wire:key="qr-{{ $loop->index }}" wire:click="quickReply({{ \Illuminate\Support\Js::from($text) }})">
                                            {{ $label }}
                                        </x-ui.button>
                                    @endforeach
                                </div>
                            @endif
                            <x-catalog.form-row>
                                <x-inputsform.input
                                    span="long"
                                    name="reply"
                                    :placeholder="__('client.conversations.reply_placeholder')"
                                    :aria-label="__('client.conversations.reply_placeholder')"
                                    wire:model="reply"
                                    wire:keydown.enter="sendReply"
                                />
                                <div class="flex flex-none items-center gap-2 self-center">
                                    <x-ui.button variant="secondary" size="sm" icon="pencil" class="data-loading:opacity-50" wire:click="saveNote">
                                        {{ __('client.conversations.note') }}
                                    </x-ui.button>
                                    <x-ui.button variant="primary" size="sm" icon="send" class="data-loading:opacity-50" wire:click="sendReply">
                                        {{ __('client.conversations.send') }}
                                    </x-ui.button>
                                </div>
                            </x-catalog.form-row>
                        </div>
                    @else
                        <p class="bd-subtle text-muted flex items-center gap-2 border-t p-3 text-xs">
                            <x-icon name="lock" :size="14" />
                            {{ __('client.conversations.read_only') }}
                        </p>
                    @endif
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
