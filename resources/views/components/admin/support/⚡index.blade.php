<?php

use App\Actions\Support\AnswerSupportTicket;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Livewire\Forms\Admin\SupportBlockForm;
use App\Livewire\Forms\Admin\SupportNoteForm;
use App\Livewire\Forms\Admin\SupportReplyForm;
use App\Models\HelpArticle;
use App\Models\Menu;
use App\Models\SupportTicket;
use App\Models\User;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What the businesses report, by arrival: the queue is the screen, and a report
 * opens as a work panel beside it, never as a page of its own. The oldest
 * unanswered sits at the top, and what could not be solved says what is missing.
 */
new class extends Component
{
    use HasNotifications;

    public SupportReplyForm $reply;

    public SupportNoteForm $note;

    public SupportBlockForm $block;

    /** Which queue: answer, waiting, blocked, resolved — or the help analysis. */
    #[Url(as: 'cola', except: 'answer')]
    public string $queue = 'answer';

    #[Url(as: 'tipo', except: '')]
    public string $kind = '';

    #[Url(as: 'negocio', except: '')]
    public string $business = '';

    #[Url(as: 'responsable', except: '')]
    public string $who = '';

    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    /** The report in the work panel; in the URL, so a link lands on the same case. */
    #[Url(as: 'reporte', except: null)]
    public ?int $open = null;

    /** Inside the panel: thread, notes or customer. */
    public string $panel = 'thread';

    /** Whether the hand-over form replaces the composer. */
    public bool $blocking = false;

    /** The open report's assignee and priority, as the selects hold them. */
    public string $assignedTo = '';

    public string $priority = '';

    /** An help article picked to paste into the answer. */
    public string $article = '';

    public function mount(): void
    {
        $this->reply->setup();
        $this->note->setup();
        $this->block->setup();
        $this->syncPanel();
    }

    /** @return Collection<int, SupportTicket> */
    #[Computed]
    public function tickets(): Collection
    {
        return SupportTicket::inbox(
            $this->queue,
            $this->kind === '' ? null : $this->kind,
            $this->business === '' ? null : (int) $this->business,
            $this->search,
            $this->who === '' ? null : $this->who,
        );
    }

    /** @return array{answer: int, waiting: int, blocked: int, resolved: int, late: int, blockedLate: int} */
    #[Computed]
    public function counts(): array
    {
        return SupportTicket::queueCounts();
    }

    /** The one that has waited longest for us, which is the first call of the day. */
    #[Computed]
    public function oldest(): ?SupportTicket
    {
        return SupportTicket::oldestUnanswered();
    }

    /** The report in the panel, with what the panel reads already loaded. */
    #[Computed]
    public function ticket(): ?SupportTicket
    {
        return $this->open === null ? null : SupportTicket::opened($this->open);
    }

    /** @return array<int, string> */
    #[Computed]
    public function reporters(): array
    {
        return SupportTicket::reporters();
    }

    /** @return array<int, string> */
    #[Computed]
    public function team(): array
    {
        return User::supportTeam();
    }

    /** Whether the list is narrowed: an empty result then means "nothing matches", not "nothing came in". */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->kind !== '' || $this->business !== '' || $this->who !== '' || trim($this->search) !== '';
    }

    public function selectQueue(string $queue): void
    {
        $this->queue = in_array($queue, ['answer', 'waiting', 'blocked', 'resolved', 'help'], true) ? $queue : 'answer';
        unset($this->tickets);
    }

    public function clearFilters(): void
    {
        $this->reset('kind', 'business', 'who', 'search');
        unset($this->tickets, $this->isFiltered);
    }

    public function openTicket(int $id): void
    {
        $this->open = $id;
        $this->panel = 'thread';
        $this->blocking = false;
        $this->article = '';
        $this->reply->setup();
        $this->note->setup();
        $this->block->setup();
        $this->syncPanel();
        unset($this->ticket);
    }

    public function closeTicket(): void
    {
        $this->open = null;
        $this->blocking = false;
        unset($this->ticket);
    }

    public function showPanel(string $panel): void
    {
        $this->panel = in_array($panel, ['thread', 'notes', 'customer'], true) ? $panel : 'thread';
    }

    /** Who has it: picking somebody on a new report is what takes it in hand. */
    public function updatedAssignedTo(string $value): void
    {
        $ticket = SupportTicket::locate((int) $this->open);

        if ($ticket === null) {
            return;
        }

        $assignee = ctype_digit($value) && array_key_exists((int) $value, $this->team) ? (int) $value : null;

        $ticket->update([
            'assigned_to' => $assignee,
            ...($assignee !== null && $ticket->status === SupportTicketStatus::New ? ['status' => SupportTicketStatus::Open] : []),
        ]);

        $this->refresh();
    }

    public function updatedPriority(string $value): void
    {
        $ticket = SupportTicket::locate((int) $this->open);
        $priority = SupportTicketPriority::tryFrom($value);

        if ($ticket === null || $priority === null) {
            return;
        }

        $ticket->update(['priority' => $priority]);

        $this->refresh();
    }

    /** Pastes an article into the answer: the guide the business needs, not a retyped paragraph. */
    public function updatedArticle(string $slug): void
    {
        $title = $this->articles[$slug] ?? null;

        if ($title === null) {
            return;
        }

        $this->reply->body = trim($this->reply->body."\n".__('support.admin.article_line', [
            'title' => $title,
            'url' => route('help', ['buscar' => $title]),
        ]));
        $this->article = '';
    }

    public function sendReply(): void
    {
        $ticket = SupportTicket::locate((int) $this->open);

        if ($ticket === null) {
            return;
        }

        $this->dispatchNotification($this->reply->save($ticket, auth()->user()));
        $this->refresh();
    }

    public function saveNote(): void
    {
        $ticket = SupportTicket::locate((int) $this->open);

        if ($ticket === null) {
            return;
        }

        $this->dispatchNotification($this->note->save($ticket, auth()->user()));
        unset($this->ticket);
    }

    public function startBlock(): void
    {
        $this->block->setup();
        $this->blocking = true;
    }

    public function cancelBlock(): void
    {
        $this->blocking = false;
    }

    public function saveBlock(): void
    {
        $ticket = SupportTicket::locate((int) $this->open);

        if ($ticket === null) {
            return;
        }

        $notification = $this->block->save($ticket, auth()->user());
        $this->dispatchNotification($notification);

        if ($notification->type === NotificationType::Success) {
            $this->blocking = false;
            $this->queue = 'blocked';
            $this->refresh();
        }
    }

    public function resolve(): void
    {
        $ticket = SupportTicket::locate((int) $this->open);

        if ($ticket === null) {
            return;
        }

        // Resolving is not just a column: it rings her bell.
        app(AnswerSupportTicket::class)->resolve($ticket);
        $this->dispatchNotification(new NotificationDto(__('support.admin.saved'), NotificationType::Success));
        $this->refresh();
    }

    public function reopen(): void
    {
        $ticket = SupportTicket::locate((int) $this->open);

        if ($ticket === null) {
            return;
        }

        $ticket->update(['status' => SupportTicketStatus::Open, 'resolved_at' => null]);
        $this->dispatchNotification(new NotificationDto(__('support.admin.reopened'), NotificationType::Success));
        $this->refresh();
    }

    /** What every change in the panel owes the screen behind it: the lists and the counts it feeds. */
    private function refresh(): void
    {
        unset($this->tickets, $this->ticket, $this->counts, $this->oldest, $this->painPoints);
        $this->syncPanel();
    }

    /** Puts the open report's own values in the selects, so they never show another report's. */
    private function syncPanel(): void
    {
        $ticket = $this->open === null ? null : SupportTicket::locate($this->open);

        $this->assignedTo = (string) ($ticket?->assigned_to ?? '');
        $this->priority = $ticket?->priority->value ?? '';
    }

    /**
     * The screens reported most, so a list of single rows still shows which
     * one is the problem.
     *
     * @return SupportCollection<int, object>
     */
    #[Computed]
    public function painPoints(): SupportCollection
    {
        return SupportTicket::painPoints();
    }

    /**
     * The answers that are not answering: a failing article is tomorrow's ticket.
     *
     * @return Collection<int, HelpArticle>
     */
    #[Computed]
    public function failingArticles(): Collection
    {
        return HelpArticle::failing();
    }

    /** Written long ago and never touched: an outdated answer is a trap. */
    #[Computed]
    public function staleArticles(): Collection
    {
        return HelpArticle::stale();
    }

    /**
     * @return array{opened: int, tickets: int}
     */
    #[Computed]
    public function deflection(): array
    {
        return HelpArticle::deflection();
    }

    /** What the help tab wants her eyes on, for its badge. */
    #[Computed]
    public function helpAttention(): int
    {
        return $this->failingArticles->count() + $this->staleArticles->count();
    }

    /** The articles she can paste into an answer. @return array<string, string> */
    #[Computed]
    public function articles(): array
    {
        return HelpArticle::shelf()->pluck('title', 'slug')->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function kinds(): array
    {
        return collect(SupportTicketKind::cases())
            ->mapWithKeys(fn (SupportTicketKind $case): array => [$case->value => __('support.kinds.'.$case->value)])
            ->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function whoOptions(): array
    {
        return ['none' => __('support.admin.unassigned')] + array_map('strval', $this->team);
    }

    public function screenName(?string $route): string
    {
        return $route === null ? __('support.no_screen') : (Menu::titleFor($route) ?? $route);
    }

    public function fileUrl(string $path): string
    {
        return Storage::disk('public')->url($path);
    }

    public function render(): View
    {
        // A full-page admin screen, so this title DOES reach the tab.
        return $this->view()->title(__('support.admin.title'));
    }
};
?>

<div>
    <x-ui.page-head :title="__('support.admin.title')" :sub="__('support.admin.sub')">
        <x-slot:inline>
            <span class="status-tag">{{ trans_choice('support.admin.count', $this->counts['answer'] + $this->counts['waiting'] + $this->counts['blocked'], ['count' => $this->counts['answer'] + $this->counts['waiting'] + $this->counts['blocked']]) }}</span>
        </x-slot:inline>

        {{-- Every figure here is counted on load, so it carries the moment it
        was read (atendiadesign §7.1); and the one that matters first is how
        long the longest-waiting person has been waiting. --}}
        <span class="sup-age">
            @if ($this->oldest !== null)
                {{ __('support.admin.oldest', ['time' => $this->oldest->waitLabel()]) }} ·
            @endif
            {{ __('admin.home.as_of', ['date' => now()->format('d/m/Y H:i')]) }}
        </span>
    </x-ui.page-head>

    {{-- The queues are server state, so they are not x-ui.tabs (which keeps its
    own in the browser): the one you are on is in the URL, and a link lands on it. --}}
    <div role="tablist" class="tabs sup-queues">
        @foreach (['answer', 'waiting', 'blocked', 'resolved'] as $name)
            <button type="button" role="tab" wire:click="selectQueue('{{ $name }}')" wire:key="queue-{{ $name }}"
                class="tab {{ $queue === $name ? 'tab-active' : '' }}" aria-selected="{{ $queue === $name ? 'true' : 'false' }}">
                <span>{{ __('support.admin.tabs.'.$name) }}</span>
                <span class="tab-badge {{ ($name === 'answer' && $this->counts['late'] > 0) || ($name === 'blocked' && $this->counts['blockedLate'] > 0) ? 'is-late' : '' }}">{{ $this->counts[$name] }}</span>
            </button>
        @endforeach
        <button type="button" role="tab" wire:click="selectQueue('help')" wire:key="queue-help"
            class="tab {{ $queue === 'help' ? 'tab-active' : '' }}" aria-selected="{{ $queue === 'help' ? 'true' : 'false' }}">
            <span>{{ __('support.admin.tabs.help') }}</span>
            @if ($this->helpAttention > 0)
                <span class="tab-badge">{{ $this->helpAttention }}</span>
            @endif
        </button>
    </div>

    @if ($queue !== 'help')
        <x-ui.card class="p-5">
            {{-- A toolbar is a form-row: the field widths are declared by what
            each one holds, and the row reaches the right edge. --}}
            <x-catalog.form-row>
                <x-inputsform.input
                    span="text"
                    size="s"
                    type="search"
                    name="search"
                    :label="__('support.admin.search')"
                    :placeholder="__('support.admin.search_placeholder')"
                    wire:model.live.debounce.300ms="search"
                />

                <x-inputsform.combobox
                    span="short"
                    size="s"
                    name="kind"
                    :label="__('support.fields.kind')"
                    :value="$kind"
                    :placeholder="__('support.admin.all_kinds')"
                    :options="$this->kinds"
                    wire:model.live="kind"
                />

                <x-inputsform.combobox
                    span="text"
                    size="s"
                    name="business"
                    :label="__('support.admin.business')"
                    :value="$business"
                    :placeholder="__('support.admin.all_businesses')"
                    :options="$this->reporters"
                    wire:model.live="business"
                />

                <x-inputsform.combobox
                    span="short"
                    size="s"
                    name="who"
                    :label="__('support.admin.assignee')"
                    :value="$who"
                    :placeholder="__('support.admin.all_assignees')"
                    :options="$this->whoOptions"
                    wire:model.live="who"
                />

                @if ($this->isFiltered)
                    <x-ui.button variant="ghost" size="sm" wire:click="clearFilters">{{ __('support.admin.clear_filters') }}</x-ui.button>
                @endif
            </x-catalog.form-row>

            @if ($this->tickets->isEmpty())
                {{-- Three different truths, said differently: nothing came in yet,
                the filters hide everything, or this queue is simply clear. --}}
                @if ($this->isFiltered)
                    <x-ui.empty-state framed icon="search" :title="__('support.admin.no_match_title')" :body="__('support.admin.no_match_body')">
                        <x-ui.button size="sm" wire:click="clearFilters">{{ __('support.admin.clear_filters') }}</x-ui.button>
                    </x-ui.empty-state>
                @elseif (array_sum([$this->counts['answer'], $this->counts['waiting'], $this->counts['blocked'], $this->counts['resolved']]) === 0)
                    <x-ui.empty-state framed icon="life-buoy" :title="__('support.admin.empty_title')" :body="__('support.admin.empty_body')" />
                @else
                    <x-ui.empty-state framed icon="check-circle" :title="__('support.admin.empty_queue.'.$queue.'.title')" :body="__('support.admin.empty_queue.'.$queue.'.body')" />
                @endif
            @else
                <div class="pay-table-wrap">
                    <table class="pay-table sup-table">
                        <thead>
                            <tr>
                                <th>{{ __('support.admin.columns.wait') }}</th>
                                <th>{{ __('support.admin.columns.report') }}</th>
                                <th>{{ __('support.admin.business') }}</th>
                                @if ($queue === 'blocked')
                                    <th>{{ __('support.admin.columns.missing') }}</th>
                                    <th>{{ __('support.admin.columns.follows') }}</th>
                                @else
                                    <th>{{ __('support.admin.assignee') }}</th>
                                    <th>{{ __('support.admin.priority') }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->tickets as $ticket)
                                @php($client = $ticket->customer())
                                {{-- Over her limit the row is painted, not only tagged: a
                                tag is read, a row is seen from the end of the list. --}}
                                <tr wire:key="ticket-{{ $ticket->id }}" wire:click="openTicket({{ $ticket->id }})"
                                    @class(['is-over' => $ticket->isLate(), 'sup-open' => $open === $ticket->id])>
                                    <td class="sup-wait font-mono" data-label="{{ __('support.admin.columns.wait') }}">
                                        @if ($ticket->waitLabel() !== null)
                                            <span class="aiu-cost-now">{{ $ticket->waitLabel() }}</span>
                                            @if ($ticket->isOverdue())
                                                <span class="status-tag is-danger">{{ __('support.admin.overdue', ['hours' => $ticket->overdueHours()]) }}</span>
                                            @elseif ($ticket->status === SupportTicketStatus::Waiting)
                                                <span class="aiu-note">{{ __('support.admin.waiting_business') }}</span>
                                            @endif
                                        @elseif ($ticket->resolvedInLabel() !== null)
                                            <span class="aiu-note">{{ __('support.admin.resolved_in', ['time' => $ticket->resolvedInLabel()]) }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="is-name" data-label="{{ __('support.admin.columns.report') }}">
                                        <span class="sup-body" data-testid="sup-expand-{{ $ticket->id }}">{{ $ticket->excerpt() }}</span>
                                        <span class="sup-code">{{ $ticket->code }} · {{ __('support.kinds.'.$ticket->kind->value) }} · {{ $this->screenName($ticket->screen) }}</span>
                                    </td>

                                    <td data-label="{{ __('support.admin.business') }}">
                                        @if ($ticket->business_id !== null)
                                            <a class="row-link" wire:navigate x-on:click.stop href="{{ route('admin.businesses', ['negocio' => $ticket->business_id]) }}">{{ $ticket->business?->name }}</a>
                                            <span class="aiu-note">{{ $client->plan ?? '—' }} · {{ $client->payment['label'] }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    @if ($queue === 'blocked')
                                        <td data-label="{{ __('support.admin.columns.missing') }}">
                                            <span class="sup-body">{{ $ticket->blocked_missing }}</span>
                                        </td>
                                        <td data-label="{{ __('support.admin.columns.follows') }}">
                                            <span class="name">{{ $ticket->blocked_owner }}</span>
                                            @if ($ticket->isBlockedLate())
                                                <span class="status-tag is-danger">{{ __('support.admin.block.late', ['date' => $ticket->blocked_due->format('d/m')]) }}</span>
                                            @else
                                                <span class="aiu-note">{{ __('support.admin.block.until', ['date' => $ticket->blocked_due?->format('d/m')]) }}</span>
                                            @endif
                                        </td>
                                    @else
                                        <td data-label="{{ __('support.admin.assignee') }}">
                                            @if ($ticket->assignee !== null)
                                                <span class="sup-who"><x-ui.avatar :name="$ticket->assignee->name" size="xs" />{{ $ticket->assignee->name }}</span>
                                            @else
                                                <span class="aiu-note">{{ __('support.admin.unassigned') }}</span>
                                            @endif
                                        </td>
                                        <td data-label="{{ __('support.admin.priority') }}">
                                            <span class="status-tag {{ $ticket->priority->tone() }}">{{ $ticket->priority->label() }}</span>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    @else
        {{-- The help is part of the same job (a failing article is tomorrow's
        ticket), but it is read once a week and the queue is read all day. --}}
        @if ($this->deflection['opened'] === 0 && $this->staleArticles->isEmpty() && $this->failingArticles->isEmpty() && $this->painPoints->isEmpty())
            <x-ui.card class="p-5">
                <x-ui.empty-state framed icon="life-buoy" :title="__('support.admin.help_empty_title')" :body="__('support.admin.help_empty_body')" />
            </x-ui.card>
        @endif

        @if ($this->deflection['opened'] > 0)
            {{-- The only number that says whether any of the help works: of all
            the answers read, how many still ended in a report. --}}
            <x-ui.card class="p-5 mb-3">
                <p class="sup-meta">{{ __('support.admin.deflection_title') }}</p>
                {{-- Two choices, not one sentence: "1 terminaron" is the kind of
                dangling grammar nobody reads twice but everybody notices. --}}
                <p class="sup-body">
                    {{ trans_choice('support.admin.deflection_opened', $this->deflection['opened'], ['count' => $this->deflection['opened']]) }}
                    {{ trans_choice('support.admin.deflection_tickets', $this->deflection['tickets'], ['count' => $this->deflection['tickets']]) }}
                </p>
            </x-ui.card>
        @endif

        @if ($this->failingArticles->isNotEmpty())
            <x-ui.card class="p-5 mb-3">
                <p class="sup-meta">{{ __('support.admin.failing_title') }}</p>
                <div class="support-kinds">
                    @foreach ($this->failingArticles as $article)
                        <a class="support-chip" href="{{ route('help', ['buscar' => $article->title]) }}"
                            wire:navigate wire:key="failing-{{ $article->id }}">
                            {{ $article->title }}
                            <strong class="font-mono">{{ $article->unhelpful_count }}</strong>
                        </a>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        @if ($this->staleArticles->isNotEmpty())
            <x-ui.card class="p-5 mb-3">
                <p class="sup-meta">{{ __('support.admin.stale_title') }}</p>
                <div class="support-kinds">
                    @foreach ($this->staleArticles as $article)
                        <a class="support-chip" href="{{ route('help', ['buscar' => $article->title]) }}"
                            wire:navigate wire:key="stale-{{ $article->id }}">
                            {{ $article->title }}
                            <span class="sup-age">{{ $article->updated_at?->diffForHumans() }}</span>
                        </a>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        @if ($this->painPoints->isNotEmpty())
            {{-- The same screen reported over and over is the thing to fix, and a
            list of single rows never shows that. --}}
            <x-ui.card class="p-5 mb-3">
                <p class="sup-meta">{{ __('support.admin.pain_title') }}</p>
                <div class="support-kinds">
                    @foreach ($this->painPoints as $point)
                        <span class="support-chip">
                            {{ $this->screenName($point->screen) }}
                            <strong class="font-mono">{{ $point->total }}</strong>
                        </span>
                    @endforeach
                </div>
            </x-ui.card>
        @endif
    @endif

    @if ($this->ticket !== null)
        @php($ticket = $this->ticket)
        @php($customer = $ticket->customer())
        <x-ui.slide-over
            class="is-wide"
            x-on:slide-over-close="$wire.closeTicket()"
            :title="$ticket->business?->name"
            :subtitle="$ticket->code.' · '.__('support.kinds.'.$ticket->kind->value).' · '.$this->screenName($ticket->screen)"
            :stacked-footer="true"
        >
            <div class="sup-panel">
                {{-- Who has it and how soon: the two things that change what happens next. --}}
                <div class="sup-meta-row">
                    <span class="status-tag {{ $ticket->status->tone() === 'warning' ? 'is-warning' : 'is-brand' }}">{{ $ticket->status->adminLabel() }}</span>
                    @if ($ticket->isOverdue())
                        <span class="status-tag is-danger">{{ __('support.admin.overdue_long', ['hours' => $ticket->overdueHours()]) }}</span>
                    @endif
                    @if ($ticket->isBlockedLate())
                        <span class="status-tag is-danger">{{ __('support.admin.block.late', ['date' => $ticket->blocked_due->format('d/m')]) }}</span>
                    @endif
                    @if ($ticket->waitLabel() !== null)
                        <span class="sup-age">{{ __('support.admin.waits', ['time' => $ticket->waitLabel()]) }}</span>
                    @endif
                </div>

                <x-catalog.form-row>
                    <x-inputsform.combobox
                        span="text"
                        size="s"
                        name="assignedTo"
                        :label="__('support.admin.assignee')"
                        :value="$assignedTo"
                        :placeholder="__('support.admin.unassigned')"
                        :options="$this->team"
                        wire:model.live="assignedTo"
                    />

                    <x-inputsform.combobox
                        span="text"
                        size="s"
                        name="priority"
                        :label="__('support.admin.priority')"
                        :value="$priority"
                        :options="SupportTicketPriority::options()"
                        wire:model.live="priority"
                    />
                </x-catalog.form-row>

                <div role="tablist" class="tabs">
                    @foreach (['thread' => __('support.admin.panel.thread'), 'notes' => __('support.admin.panel.notes'), 'customer' => __('support.admin.panel.customer')] as $key => $label)
                        <button type="button" role="tab" wire:click="showPanel('{{ $key }}')" wire:key="panel-{{ $key }}"
                            class="tab {{ $panel === $key ? 'tab-active' : '' }}" aria-selected="{{ $panel === $key ? 'true' : 'false' }}">
                            <span>{{ $label }}</span>
                            @if ($key === 'notes' && $ticket->messages->where('is_internal', true)->isNotEmpty())
                                <span class="tab-badge">{{ $ticket->messages->where('is_internal', true)->count() }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                @if ($panel === 'thread')
                    {{-- The report is the first message; what the team said follows it. --}}
                    <div class="sup-msg">
                        <p class="sup-bubble">{{ $ticket->body }}</p>
                        <span class="sup-meta">
                            <span>{{ $ticket->user?->name ?? $ticket->business?->name }}</span>
                            <span>{{ $ticket->created_at?->format('d/m/Y H:i') }}</span>
                            @if ($ticket->attachment_path !== null)
                                <a href="{{ $this->fileUrl($ticket->attachment_path) }}" target="_blank" rel="noopener">{{ __('support.admin.attachment') }}</a>
                            @endif
                        </span>
                    </div>

                    @foreach ($ticket->messages->where('is_internal', false) as $message)
                        <div class="sup-msg is-ours" wire:key="message-{{ $message->id }}">
                            <p class="sup-bubble">{{ $message->body }}</p>
                            <span class="sup-meta">
                                <span>{{ $message->author?->name ?? __('support.admin.the_team') }}</span>
                                <span>{{ $message->created_at?->format('d/m H:i') }}</span>
                                @if ($message->delivery !== null)
                                    <span class="status-tag {{ $message->delivery->reached() ? 'is-brand' : 'is-danger' }}">{{ $message->delivery->label() }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach

                    @if ($reply->failedToLeave())
                        {{-- What the person just did did not reach the business, and the
                        status did not move: said where she is looking, not in a toast. --}}
                        <x-ui.alert variant="danger" icon="triangle-alert">{{ \App\Enums\SupportDelivery::from($reply->delivery)->outcome() }}</x-ui.alert>
                    @endif

                    @if ($ticket->status === SupportTicketStatus::Blocked)
                        <div class="sup-handoff">
                            <strong>{{ __('support.admin.block.title') }}</strong>
                            <dl class="sup-evidence">
                                <dt>{{ __('support.admin.block.tried') }}</dt>
                                <dd>{{ $ticket->blocked_tried }}</dd>
                                <dt>{{ __('support.admin.block.missing') }}</dt>
                                <dd>{{ $ticket->blocked_missing }}</dd>
                                <dt>{{ __('support.admin.block.owner') }}</dt>
                                <dd>{{ $ticket->blocked_owner }}</dd>
                                <dt>{{ __('support.admin.block.due') }}</dt>
                                <dd>{{ $ticket->blocked_due?->format('d/m/Y') }}</dd>
                            </dl>
                        </div>
                    @endif

                    {{-- What the widget captured, read like a person reads it. --}}
                    @php($evidence = $ticket->evidence())
                    @unless ($evidence->isEmpty)
                        <details class="sup-details">
                            <summary>{{ __('support.admin.context') }}</summary>
                            <dl class="sup-evidence">
                                @foreach ($evidence->rows as $row)
                                    <dt>{{ $row['label'] }}</dt>
                                    <dd @class(['font-mono' => $row['mono']])>{{ $row['value'] }}</dd>
                                @endforeach
                            </dl>
                            @if ($evidence->errors !== [])
                                <div class="sup-errors">
                                    <span class="status-tag is-danger">{{ trans_choice('support.admin.evidence.errors', count($evidence->errors), ['count' => count($evidence->errors)]) }}</span>
                                    @foreach ($evidence->errors as $error)
                                        <code>{{ $error }}</code>
                                    @endforeach
                                </div>
                            @endif
                        </details>
                    @endunless
                @elseif ($panel === 'notes')
                    <p class="sup-meta">{{ __('support.admin.notes_hint') }}</p>

                    @forelse ($ticket->messages->where('is_internal', true) as $message)
                        <div class="sup-msg is-note" wire:key="note-{{ $message->id }}">
                            <p class="sup-bubble">{{ $message->body }}</p>
                            <span class="sup-meta">
                                <span>{{ $message->author?->name ?? __('support.admin.the_team') }}</span>
                                <span>{{ $message->created_at?->format('d/m H:i') }}</span>
                            </span>
                        </div>
                    @empty
                        <x-ui.empty-state compact icon="scroll-text" :title="__('support.admin.notes_empty_title')" :body="__('support.admin.notes_empty')" />
                    @endforelse

                    <x-catalog.form-row>
                        <x-inputsform.textarea
                            span="full"
                            name="body"
                            :label="__('support.admin.note')"
                            :placeholder="__('support.admin.note_placeholder')"
                            :rows="3"
                            maxlength="2000"
                            wire:model="note.body"
                        />
                    </x-catalog.form-row>
                    <span class="support-actions">
                        <x-ui.button size="sm" wire:click="saveNote" wire:loading.attr="disabled">{{ __('support.admin.note_save') }}</x-ui.button>
                    </span>
                @else
                    {{-- Before writing: whether this customer pays, whether its WhatsApp
                    works, and where an answer will actually arrive. --}}
                    <dl class="sup-evidence">
                        <dt>{{ __('support.admin.business') }}</dt>
                        <dd>{{ $customer->name }}</dd>
                        <dt>{{ __('support.admin.customer.plan') }}</dt>
                        <dd>{{ $customer->plan ?? '—' }}</dd>
                        <dt>{{ __('support.admin.customer.payment_label') }}</dt>
                        <dd><span class="status-tag {{ $customer->payment['tone'] }}">{{ $customer->payment['label'] }}</span></dd>
                        <dt>{{ __('support.admin.customer.whatsapp_label') }}</dt>
                        <dd><span class="status-tag {{ $customer->whatsapp['tone'] }}">{{ $customer->whatsapp['label'] }}</span></dd>
                        <dt>{{ __('support.admin.customer.reach') }}</dt>
                        <dd>
                            @if ($customer->hasAlertNumber)
                                {{ __('support.admin.customer.reach_whatsapp') }}
                            @elseif ($customer->fallbackEmail !== null)
                                {{ __('support.admin.customer.reach_email', ['email' => $customer->fallbackEmail]) }}
                            @else
                                <span class="status-tag is-danger">{{ __('support.admin.customer.reach_none') }}</span>
                            @endif
                        </dd>
                    </dl>

                    <p class="sup-meta">{{ __('support.admin.customer.others') }}</p>
                    @forelse ($customer->others as $other)
                        <button type="button" class="support-chip" wire:click="openTicket({{ $other->id }})" wire:key="other-{{ $other->id }}">
                            {{ $other->code }} · {{ $other->status->adminLabel() }} · {{ $other->created_at?->diffForHumans() }}
                        </button>
                    @empty
                        <p class="sup-body">{{ __('support.admin.customer.first') }}</p>
                    @endforelse

                    <span class="support-actions">
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.businesses', ['negocio' => $ticket->business_id])" wire:navigate>
                            {{ __('support.admin.customer.sheet') }}
                        </x-ui.button>
                    </span>
                @endif
            </div>

            <x-slot:footer>
                @if ($ticket->status === SupportTicketStatus::Resolved || $ticket->status === SupportTicketStatus::Closed)
                    <span class="sup-body">{{ __('support.admin.resolved_in', ['time' => $ticket->resolvedInLabel() ?? '—']) }}</span>
                    <x-ui.button size="sm" variant="secondary" wire:click="reopen">{{ __('support.admin.reopen') }}</x-ui.button>
                @elseif ($blocking)
                    <div class="sup-composer">
                        <strong class="sup-body">{{ __('support.admin.block.form_title') }}</strong>
                        <x-catalog.form-row>
                            <x-inputsform.textarea span="full" name="tried" required :label="__('support.admin.block.tried')" :hint="__('support.admin.block.tried_hint')" :rows="2" maxlength="2000" wire:model="block.tried" />
                        </x-catalog.form-row>
                        <x-catalog.form-row>
                            <x-inputsform.textarea span="full" name="missing" required :label="__('support.admin.block.missing')" :hint="__('support.admin.block.missing_hint')" :rows="2" maxlength="2000" wire:model="block.missing" />
                        </x-catalog.form-row>
                        <x-catalog.form-row>
                            <x-inputsform.combobox span="text" size="s" name="owner" required :label="__('support.admin.block.owner')" :value="$block->owner" :options="SupportBlockForm::owners()" wire:model="block.owner" />
                            <x-inputsform.datepicker span="text" name="due" required :label="__('support.admin.block.due')" :value="$block->due" wire:model="block.due" />
                        </x-catalog.form-row>
                        <x-catalog.form-row>
                            <x-inputsform.textarea span="full" name="notice" :label="__('support.admin.block.notice')" :hint="__('support.admin.block.notice_hint')" :rows="2" maxlength="900" wire:model="block.notice" />
                        </x-catalog.form-row>
                        <span class="support-actions">
                            <x-ui.button variant="danger" wire:click="cancelBlock">{{ __('support.admin.cancel') }}</x-ui.button>
                            <x-ui.button variant="primary" wire:click="saveBlock" wire:loading.attr="disabled">{{ __('support.admin.block.save') }}</x-ui.button>
                        </span>
                    </div>
                @else
                    <div class="sup-composer">
                        <x-catalog.form-row>
                            <x-inputsform.textarea
                                span="full"
                                name="body"
                                :label="__('support.admin.reply')"
                                :hint="__('support.admin.reply_hint')"
                                :rows="3"
                                maxlength="900"
                                counter
                                wire:model="reply.body"
                            />
                        </x-catalog.form-row>
                        <x-catalog.form-row>
                            <x-inputsform.combobox
                                span="text"
                                size="s"
                                name="article"
                                :label="__('support.admin.article')"
                                :value="$article"
                                :placeholder="__('support.admin.article_placeholder')"
                                :options="$this->articles"
                                wire:model.live="article"
                            />
                        </x-catalog.form-row>
                        <span class="support-actions">
                            <x-ui.button size="sm" variant="secondary" wire:click="startBlock">{{ __('support.admin.block.open') }}</x-ui.button>
                            <x-ui.button size="sm" variant="secondary" wire:click="resolve">{{ __('support.admin.resolve') }}</x-ui.button>
                            <x-ui.button variant="primary" size="sm" wire:click="sendReply" wire:loading.attr="disabled">{{ __('support.admin.send_reply') }}</x-ui.button>
                        </span>
                    </div>
                @endif
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
</div>
