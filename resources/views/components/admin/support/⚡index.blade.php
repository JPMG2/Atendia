<?php

use App\Actions\Support\AnswerSupportTicket;
use App\Classes\Main\SupportCase;
use App\Classes\Main\SupportHelpDigest;
use App\Classes\Main\SupportQueue;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Livewire\Forms\Admin\SupportBlockForm;
use App\Livewire\Forms\Admin\SupportNoteForm;
use App\Livewire\Forms\Admin\SupportReplyForm;
use App\Models\HelpArticle;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\User;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
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

    /** A saved reply picked to paste into the answer. */
    public string $saved = '';

    public function mount(): void
    {
        $this->reply->setup();
        $this->note->setup();
        $this->block->setup();
        $this->syncPanel();
    }

    /** The queue as the API reads it too: counts, header stamps, rows and the words of both. */
    #[Computed]
    public function desk(): SupportQueue
    {
        return new SupportQueue($this->queue, $this->kind, $this->business, $this->who, $this->search);
    }

    /** What the export menu carries to the report: the queue and filters on screen. */
    #[Computed]
    public function exportParams(): array
    {
        return ['cola' => $this->queue, 'tipo' => $this->kind, 'negocio' => $this->business, 'responsable' => $this->who, 'buscar' => $this->search];
    }

    /** The report in the panel, with what the panel reads already loaded. */
    #[Computed]
    public function ticket(): ?SupportTicket
    {
        return $this->open === null ? null : SupportTicket::opened($this->open);
    }

    /** The open report as the API reads it too: its state, thread, notes and customer, with the words of both. */
    #[Computed]
    public function case(): ?SupportCase
    {
        return $this->ticket === null ? null : new SupportCase($this->ticket);
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
        unset($this->desk);
    }

    /** One click for what is hers: the shortcut toggles the assignee filter on her own id. */
    public function toggleMine(): void
    {
        $this->who = $this->isMine ? '' : (string) auth()->id();
        unset($this->desk, $this->isFiltered, $this->isMine);
    }

    #[Computed]
    public function isMine(): bool
    {
        return $this->who === (string) auth()->id();
    }

    public function clearFilters(): void
    {
        $this->reset('kind', 'business', 'who', 'search');
        unset($this->desk, $this->isFiltered);
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
        unset($this->ticket, $this->case);
    }

    public function closeTicket(): void
    {
        $this->open = null;
        $this->blocking = false;
        unset($this->ticket, $this->case);
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

    /** Pastes a saved reply into the answer: written once in the admin, adjusted here before sending. */
    public function updatedSaved(string $id): void
    {
        $body = ctype_digit($id) ? SupportReply::bodyOf((int) $id) : null;

        if ($body !== null) {
            $this->reply->body = trim($this->reply->body."\n".$body);
        }

        $this->saved = '';
    }

    /** Pastes the AI's draft into the answer: a start she reads and edits, never something sent on its own. */
    public function useDraft(): void
    {
        $draft = (string) ($this->ticket?->ai_suggestion['draft'] ?? '');

        if ($draft !== '') {
            $this->reply->body = trim($this->reply->body."\n".$draft);
        }
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
        unset($this->ticket, $this->case);
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
        unset($this->desk, $this->ticket, $this->case, $this->help);
        $this->syncPanel();
    }

    /** Puts the open report's own values in the selects, so they never show another report's. */
    private function syncPanel(): void
    {
        $ticket = $this->open === null ? null : SupportTicket::locate($this->open);

        $this->assignedTo = (string) ($ticket?->assigned_to ?? '');
        $this->priority = $ticket?->priority->value ?? '';
    }

    /** The help tab as the API reads it too: the answers that fail, the stale ones, the screens reported most. */
    #[Computed]
    public function help(): SupportHelpDigest
    {
        return new SupportHelpDigest;
    }

    /** The articles she can paste into an answer. @return array<string, string> */
    #[Computed]
    public function articles(): array
    {
        return HelpArticle::shelf()->pluck('title', 'slug')->all();
    }

    /** The saved replies she can paste into an answer. @return array<int, string> */
    #[Computed]
    public function replies(): array
    {
        return SupportReply::shelf();
    }

    /** @return array<string, string> */
    #[Computed]
    public function kinds(): array
    {
        // Every kind, switched off ones too: an old report still has to be found by it.
        return collect(SupportTicketKind::options())->pluck('label', 'value')->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function whoOptions(): array
    {
        return ['none' => __('support.admin.unassigned')] + array_map('strval', $this->team);
    }

    public function screenName(?string $route): string
    {
        return SupportQueue::screenName($route);
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
    @php($head = $this->desk->head)
    <x-ui.page-head :title="$head['title']" :sub="$head['sub']">
        <x-slot:inline>
            <span class="status-tag">{{ $head['total'] }}</span>
        </x-slot:inline>

        {{-- Every figure here is counted on load, so it carries the moment it
        was read (atendiadesign §7.1); and the one that matters first is how
        long the longest-waiting person has been waiting. --}}
        <span class="sup-age">
            @if ($head['oldest'] !== null)
                {{ $head['oldest'] }} ·
            @endif
            {{ $head['as_of'] }}
        </span>
        {{-- A median with nothing behind it is not a zero: it says why it is missing. --}}
        <span class="sup-age">{{ $head['medians'] }}</span>
        <x-ui.export-menu report="soporte" :params="$this->exportParams" />
    </x-ui.page-head>

    {{-- The queues are server state, so they are not x-ui.tabs (which keeps its
    own in the browser): the one you are on is in the URL, and a link lands on it. --}}
    <div class="sup-queue-bar">
    <div role="tablist" class="tabs sup-queues">
        @foreach ($this->desk->tabs as $tab)
            <button type="button" role="tab" wire:click="selectQueue('{{ $tab['key'] }}')" wire:key="queue-{{ $tab['key'] }}"
                class="tab {{ $queue === $tab['key'] ? 'tab-active' : '' }}" aria-selected="{{ $queue === $tab['key'] ? 'true' : 'false' }}">
                <span>{{ $tab['label'] }}</span>
                @if ($tab['count'] !== null)
                    {{-- The help tab only carries a badge when something needs eyes; the queues always do. --}}
                    <span class="tab-badge {{ $tab['late'] ? 'is-late' : '' }}">{{ $tab['count'] }}</span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Hers, one click away, at the end of the bar: a shortcut belongs with the queues, not among the filters. --}}
    @if ($queue !== 'help')
        <x-ui.button :variant="$this->isMine ? 'primary' : 'secondary'" size="sm" icon="user" wire:click="toggleMine" aria-pressed="{{ $this->isMine ? 'true' : 'false' }}">{{ __('support.admin.mine') }}</x-ui.button>
    @endif
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
                    data-key-focus="/"
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
                    <x-ui.button variant="ghost" size="sm" wire:click="clearFilters" data-key-click="c">{{ __('support.admin.clear_filters') }}</x-ui.button>
                @endif
            </x-catalog.form-row>

            @php($desk = $this->desk)
            @if ($desk->empty !== null)
                {{-- Three different truths, said differently: nothing came in yet,
                the filters hide everything, or this queue is simply clear. --}}
                @if ($this->isFiltered)
                    <x-ui.empty-state framed icon="search" :title="$desk->empty['title']" :body="$desk->empty['body']">
                        <x-ui.button size="sm" wire:click="clearFilters">{{ __('support.admin.clear_filters') }}</x-ui.button>
                    </x-ui.empty-state>
                @elseif (array_sum([$desk->counts['answer'], $desk->counts['waiting'], $desk->counts['blocked'], $desk->counts['resolved']]) === 0)
                    <x-ui.empty-state framed icon="life-buoy" :title="$desk->empty['title']" :body="$desk->empty['body']" />
                @else
                    <x-ui.empty-state framed icon="check-circle" :title="$desk->empty['title']" :body="$desk->empty['body']" />
                @endif
            @else
                @php($labels = $desk->labels)
                <div class="pay-table-wrap">
                    <table class="pay-table sup-table" data-sortable>
                        <thead>
                            <tr>
                                <th>{{ $labels['wait'] }}</th>
                                <th>{{ $labels['report'] }}</th>
                                <th>{{ $labels['business'] }}</th>
                                @if ($queue === 'blocked')
                                    <th>{{ $labels['missing'] }}</th>
                                    <th>{{ $labels['follows'] }}</th>
                                @else
                                    <th>{{ $labels['assignee'] }}</th>
                                    <th>{{ $labels['priority'] }}</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($desk->rows as $row)
                                {{-- Over her limit the row is painted, not only tagged: a
                                tag is read, a row is seen from the end of the list. --}}
                                <tr wire:key="ticket-{{ $row['id'] }}" wire:click="openTicket({{ $row['id'] }})"
                                    @class(['is-over' => $row['late'], 'sup-open' => $open === $row['id']])>
                                    <td class="sup-wait font-mono" data-label="{{ $labels['wait'] }}">
                                        @if ($row['wait']['label'] !== null)
                                            <span class="aiu-cost-now">{{ $row['wait']['label'] }}</span>
                                            @if ($row['wait']['overdue'] !== null)
                                                <span class="status-tag is-danger">{{ $row['wait']['overdue'] }}</span>
                                            @elseif ($row['wait']['note'] !== null)
                                                <span class="aiu-note">{{ $row['wait']['note'] }}</span>
                                            @endif
                                        @elseif ($row['wait']['note'] !== null)
                                            <span class="aiu-note">{{ $row['wait']['note'] }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="is-name is-key" data-label="{{ $labels['report'] }}">
                                        <span class="sup-body" data-testid="sup-expand-{{ $row['id'] }}">{{ $row['excerpt'] }}</span>
                                        <span class="sup-code">{{ $row['code'] }} · {{ $row['kind'] }} · {{ $row['screen'] }}</span>
                                    </td>

                                    <td data-label="{{ $labels['business'] }}">
                                        @if ($row['business'] !== null)
                                            <a class="row-link" wire:navigate x-on:click.stop href="{{ route('admin.businesses', ['negocio' => $row['business']['id']]) }}">{{ $row['business']['name'] }}</a>
                                            <span class="aiu-note">{{ $row['business']['plan'] }} · {{ $row['business']['payment']['label'] }}</span>
                                        @else
                                            —
                                        @endif
                                    </td>

                                    @if ($queue === 'blocked')
                                        <td data-label="{{ $labels['missing'] }}">
                                            <span class="sup-body">{{ $row['blocked']['missing'] ?? '' }}</span>
                                        </td>
                                        <td data-label="{{ $labels['follows'] }}">
                                            <span class="name">{{ $row['blocked']['owner'] ?? '' }}</span>
                                            @if ($row['blocked']['late'] ?? false)
                                                <span class="status-tag is-danger">{{ $row['blocked']['follows'] }}</span>
                                            @else
                                                <span class="aiu-note">{{ $row['blocked']['follows'] ?? '' }}</span>
                                            @endif
                                        </td>
                                    @else
                                        <td data-label="{{ $labels['assignee'] }}">
                                            @if ($row['assignee'] !== null)
                                                <span class="sup-who"><x-ui.avatar :name="$row['assignee']" size="xs" />{{ $row['assignee'] }}</span>
                                            @else
                                                <span class="aiu-note">{{ __('support.admin.unassigned') }}</span>
                                            @endif
                                        </td>
                                        <td data-label="{{ $labels['priority'] }}">
                                            <span class="status-tag is-{{ $row['priority']['tone'] }}">{{ $row['priority']['label'] }}</span>
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
        @php($help = $this->help)
        @if ($help->empty !== null)
            <x-ui.card class="p-5">
                <x-ui.empty-state framed icon="life-buoy" :title="$help->empty['title']" :body="$help->empty['body']" />
            </x-ui.card>
        @endif

        @if ($help->deflectionCard !== null)
            {{-- The only number that says whether any of the help works: of all
            the answers read, how many still ended in a report. --}}
            <x-ui.card class="p-5 mb-3">
                <p class="sup-meta">{{ $help->deflectionCard['title'] }}</p>
                <p class="sup-body">{{ $help->deflectionCard['text'] }}</p>
            </x-ui.card>
        @endif

        @if ($help->failingCard !== null)
            <x-ui.card class="p-5 mb-3">
                <p class="sup-meta">{{ $help->failingCard['title'] }}</p>
                <div class="support-kinds">
                    @foreach ($help->failingCard['rows'] as $article)
                        <a class="support-chip" href="{{ route('help', ['buscar' => $article['title']]) }}"
                            wire:navigate wire:key="failing-{{ $article['id'] }}">
                            {{ $article['title'] }}
                            <strong class="font-mono">{{ $article['count'] }}</strong>
                        </a>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        @if ($help->staleCard !== null)
            <x-ui.card class="p-5 mb-3">
                <p class="sup-meta">{{ $help->staleCard['title'] }}</p>
                <div class="support-kinds">
                    @foreach ($help->staleCard['rows'] as $article)
                        <a class="support-chip" href="{{ route('help', ['buscar' => $article['title']]) }}"
                            wire:navigate wire:key="stale-{{ $article['id'] }}">
                            {{ $article['title'] }}
                            <span class="sup-age">{{ $article['age'] }}</span>
                        </a>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        @if ($help->painCard !== null)
            {{-- The same screen reported over and over is the thing to fix, and a
            list of single rows never shows that. --}}
            <x-ui.card class="p-5 mb-3">
                <p class="sup-meta">{{ $help->painCard['title'] }}</p>
                <div class="support-kinds">
                    @foreach ($help->painCard['rows'] as $point)
                        <span class="support-chip">
                            {{ $point['screen'] }}
                            <strong class="font-mono">{{ $point['total'] }}</strong>
                        </span>
                    @endforeach
                </div>
            </x-ui.card>
        @endif
    @endif

    @if ($this->ticket !== null)
        @php($ticket = $this->ticket)
        @php($case = $this->case)
        @php($state = $case->state)
        <x-ui.slide-over
            class="is-wide"
            x-on:slide-over-close="$wire.closeTicket()"
            :title="$case->head['title']"
            :subtitle="$case->head['subtitle']"
            :stacked-footer="true"
        >
            <div class="sup-panel">
                {{-- Who has it and how soon: the two things that change what happens next. --}}
                <div class="sup-meta-row">
                    <span class="status-tag is-{{ $state['status']['tone'] }}">{{ $state['status']['label'] }}</span>
                    @if ($state['overdue'] !== null)
                        <span class="status-tag is-danger">{{ $state['overdue'] }}</span>
                    @endif
                    @if ($state['blocked_late'] !== null)
                        <span class="status-tag is-danger">{{ $state['blocked_late'] }}</span>
                    @endif
                    @if ($state['waits'] !== null)
                        <span class="sup-age">{{ $state['waits'] }}</span>
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
                    @foreach ($case->panels as $tab)
                        <button type="button" role="tab" wire:click="showPanel('{{ $tab['key'] }}')" wire:key="panel-{{ $tab['key'] }}"
                            class="tab {{ $panel === $tab['key'] ? 'tab-active' : '' }}" aria-selected="{{ $panel === $tab['key'] ? 'true' : 'false' }}">
                            <span>{{ $tab['label'] }}</span>
                            @if ($tab['badge'] !== null)
                                <span class="tab-badge">{{ $tab['badge'] }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                @if ($panel === 'thread')
                    @php($thread = $case->thread)
                    {{-- The report is the first message; what the team said follows it. --}}
                    <div class="sup-msg">
                        <p class="sup-bubble">{{ $thread['report']['body'] }}</p>
                        <span class="sup-meta">
                            <span>{{ $thread['report']['author'] }}</span>
                            <span>{{ $thread['report']['at'] }}</span>
                            @if ($thread['report']['attachment'] !== null)
                                <a href="{{ $this->fileUrl($ticket->attachment_path) }}" target="_blank" rel="noopener">{{ $thread['report']['attachment'] }}</a>
                            @endif
                        </span>
                    </div>

                    @php($ai = $case->ai)
                    @if ($ai !== null)
                        {{-- Prepared for the team only: it sits between the report and the
                        answer box, and nothing in it leaves until a person sends. --}}
                        <div class="sup-ai" data-testid="ai-suggestion">
                            <div class="sup-ai-head">
                                <x-icon name="sparkles" :size="16" />
                                <strong>{{ $ai['title'] }}</strong>
                                <span class="status-tag is-{{ $ai['confidence']['tone'] }}">{{ $ai['confidence']['label'] }}</span>
                            </div>

                            @if ($ai['summary'] !== '')
                                <p class="sup-body">{{ $ai['summary'] }}</p>
                            @endif

                            @if ($ai['suggests'] !== null || $ai['looks_like'] !== null)
                                <div class="sup-ai-chips">
                                    @if ($ai['suggests'] !== null)
                                        <span class="status-tag is-info">{{ $ai['suggests'] }}</span>
                                    @endif
                                    @if ($ai['looks_like'] !== null)
                                        <span class="status-tag is-warning">{{ $ai['looks_like'] }}</span>
                                    @endif
                                </div>
                            @endif

                            @if ($ai['draft'] !== '')
                                <p class="sup-meta">{{ $ai['draft_label'] }}</p>
                                <p class="sup-bubble sup-ai-draft">{{ $ai['draft'] }}</p>
                                @if ($ai['sources'] !== null)
                                    <p class="sup-meta">{{ $ai['sources'] }}</p>
                                @endif
                                <span class="support-actions">
                                    <x-ui.button size="sm" variant="secondary" icon="pencil" wire:click="useDraft" data-testid="ai-use-draft">{{ __('support.admin.ai.use') }}</x-ui.button>
                                </span>
                            @else
                                <p class="sup-meta">{{ $ai['no_draft'] }}</p>
                            @endif

                            @if ($ai['reason'] !== '')
                                <p class="sup-meta">{{ $ai['reason'] }}</p>
                            @endif
                            <p class="sup-meta">{{ $ai['only_team'] }}</p>
                        </div>
                    @endif

                    @foreach ($thread['answers'] as $message)
                        <div class="sup-msg is-ours" wire:key="message-{{ $message['id'] }}">
                            <p class="sup-bubble">{{ $message['body'] }}</p>
                            <span class="sup-meta">
                                <span>{{ $message['author'] }}</span>
                                <span>{{ $message['at'] }}</span>
                                @if ($message['delivery'] !== null)
                                    <span class="status-tag is-{{ $message['delivery']['tone'] }}">{{ $message['delivery']['label'] }}</span>
                                @endif
                            </span>
                        </div>
                    @endforeach

                    @if ($reply->failedToLeave())
                        {{-- What the person just did did not reach the business, and the
                        status did not move: said where she is looking, not in a toast. --}}
                        <x-ui.alert variant="danger" icon="triangle-alert">{{ \App\Enums\SupportDelivery::from($reply->delivery)->outcome() }}</x-ui.alert>
                    @endif

                    @php($handoff = $case->handoff)
                    @if ($handoff !== null)
                        <div class="sup-handoff">
                            <strong>{{ $handoff['title'] }}</strong>
                            <dl class="sup-evidence">
                                @foreach ($handoff['rows'] as $row)
                                    <dt>{{ $row['label'] }}</dt>
                                    <dd>{{ $row['value'] }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    @endif

                    {{-- What the widget captured, read like a person reads it. --}}
                    @php($context = $case->context)
                    @if ($context !== null)
                        <details class="sup-details">
                            <summary>{{ $context['title'] }}</summary>
                            <dl class="sup-evidence">
                                @foreach ($context['rows'] as $row)
                                    <dt>{{ $row['label'] }}</dt>
                                    <dd @class(['font-mono' => $row['mono']])>{{ $row['value'] }}</dd>
                                @endforeach
                            </dl>
                            @if ($context['errors'] !== null)
                                <div class="sup-errors">
                                    <span class="status-tag is-danger">{{ $context['errors']['label'] }}</span>
                                    @foreach ($context['errors']['lines'] as $error)
                                        <code>{{ $error }}</code>
                                    @endforeach
                                </div>
                            @endif
                        </details>
                    @endif
                @elseif ($panel === 'notes')
                    @php($sheet = $case->notesSheet)
                    <p class="sup-meta">{{ $sheet['hint'] }}</p>

                    @forelse ($sheet['rows'] as $note)
                        <div class="sup-msg is-note" wire:key="note-{{ $note['id'] }}">
                            <p class="sup-bubble">{{ $note['body'] }}</p>
                            <span class="sup-meta">
                                <span>{{ $note['author'] }}</span>
                                <span>{{ $note['at'] }}</span>
                            </span>
                        </div>
                    @empty
                        <x-ui.empty-state compact icon="scroll-text" :title="$sheet['empty']['title']" :body="$sheet['empty']['body']" />
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
                    @php($sheet = $case->customerSheet)
                    {{-- A business that closed its account is gone: its report stays readable, with no customer beside it. --}}
                    @if ($sheet !== null)
                    <dl class="sup-evidence">
                        @foreach ($sheet['rows'] as $row)
                            <dt>{{ $row['label'] }}</dt>
                            <dd>
                                @if ($row['tone'] !== null)
                                    <span class="status-tag is-{{ $row['tone'] }}">{{ $row['value'] }}</span>
                                @else
                                    {{ $row['value'] }}
                                @endif
                            </dd>
                        @endforeach
                    </dl>

                    <p class="sup-meta">{{ $sheet['others']['label'] }}</p>
                    @forelse ($sheet['others']['rows'] as $other)
                        <button type="button" class="support-chip" wire:click="openTicket({{ $other['id'] }})" wire:key="other-{{ $other['id'] }}">
                            {{ $other['text'] }}
                        </button>
                    @empty
                        <p class="sup-body">{{ $sheet['others']['first'] }}</p>
                    @endforelse

                    <span class="support-actions">
                        <x-ui.button size="sm" variant="secondary" :href="route('admin.businesses', ['negocio' => $sheet['business']])" wire:navigate>
                            {{ $sheet['sheet'] }}
                        </x-ui.button>
                    </span>
                    @endif
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

                            @if ($this->replies !== [])
                                <x-inputsform.combobox
                                    span="text"
                                    size="s"
                                    name="saved"
                                    :label="__('support.admin.saved_reply')"
                                    :value="$saved"
                                    :placeholder="__('support.admin.saved_reply_placeholder')"
                                    :options="$this->replies"
                                    wire:model.live="saved"
                                />
                            @endif
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

    <x-ui.key-hints>
        <kbd class="cmdk-kbd">/</kbd> {{ __('admin.keys.search') }}
        <kbd class="cmdk-kbd">c</kbd> {{ __('admin.keys.clear') }}
    </x-ui.key-hints>
</div>
