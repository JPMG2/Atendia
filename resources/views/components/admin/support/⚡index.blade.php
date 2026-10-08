<?php

use App\Actions\Support\AnswerSupportTicket;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Enums\SupportTicketKind;
use App\Enums\SupportTicketStatus;
use App\Models\HelpArticle;
use App\Models\Menu;
use App\Models\SupportTicket;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * What the businesses report, by arrival. A queue is answered in the order it
 * came in, so the oldest unanswered sits at the top and what is settled sinks.
 * The queue is the screen: what the help is doing wrong lives apart, one tab over.
 */
new class extends Component
{
    use HasNotifications;

    /** Which queue: what is pending, what is ours, what waits for the business, what is done. */
    #[Url(as: 'estado', except: 'open')]
    public string $filter = 'open';

    #[Url(as: 'tipo', except: '')]
    public string $kind = '';

    #[Url(as: 'negocio', except: '')]
    public string $business = '';

    #[Url(as: 'buscar', except: '')]
    public string $search = '';

    public ?int $expanded = null;

    /** What the admin is typing: one open reply at a time. */
    public string $reply = '';

    /** @return Collection<int, SupportTicket> */
    #[Computed]
    public function tickets(): Collection
    {
        return SupportTicket::inbox(
            $this->filter,
            $this->kind === '' ? null : $this->kind,
            $this->business === '' ? null : (int) $this->business,
            $this->search,
        );
    }

    /** The one that has waited longest for us, which is the first call of the day. */
    #[Computed]
    public function oldest(): ?SupportTicket
    {
        return SupportTicket::oldestUnanswered();
    }

    /** @return array<int, string> */
    #[Computed]
    public function reporters(): array
    {
        return SupportTicket::reporters();
    }

    /** Whether the list is narrowed: an empty result then means "nothing matches", not "nothing came in". */
    #[Computed]
    public function isFiltered(): bool
    {
        return $this->filter !== 'open' || $this->kind !== '' || $this->business !== '' || trim($this->search) !== '';
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
        $this->reply = '';
    }

    public function setStatus(int $id, string $status): void
    {
        $ticket = SupportTicket::locate($id);
        $case = SupportTicketStatus::tryFrom($status);

        if ($ticket === null || $case === null) {
            return;
        }

        if ($case === SupportTicketStatus::Resolved) {
            // Resolving is not just a column: it rings her bell.
            app(AnswerSupportTicket::class)->resolve($ticket);
        } else {
            $ticket->update([
                'status' => $case,
                'resolved_at' => $case->isOpen() ? null : now(),
            ]);
        }

        unset($this->tickets, $this->painPoints, $this->oldest);

        $this->dispatchNotification(new NotificationDto(__('support.admin.saved'), NotificationType::Success));
    }

    public function answer(int $id): void
    {
        $ticket = SupportTicket::locate($id);

        if ($ticket === null || trim($this->reply) === '') {
            return;
        }

        app(AnswerSupportTicket::class)->reply($ticket, $this->reply);

        $this->reply = '';
        unset($this->tickets, $this->painPoints, $this->oldest);

        $this->dispatchNotification(new NotificationDto(__('support.admin.answered'), NotificationType::Success));
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

    /** @return array<string, string> */
    #[Computed]
    public function statuses(): array
    {
        return collect(SupportTicketStatus::cases())
            // Her words, not the business's: "waiting for YOUR answer" reads backwards here.
            ->mapWithKeys(fn (SupportTicketStatus $case): array => [$case->value => __('support.admin.statuses.'.$case->value)])
            ->all();
    }

    /** @return array<string, string> */
    #[Computed]
    public function kinds(): array
    {
        return collect(SupportTicketKind::cases())
            ->mapWithKeys(fn (SupportTicketKind $case): array => [$case->value => __('support.kinds.'.$case->value)])
            ->all();
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
            <span class="status-tag">{{ trans_choice('support.admin.count', $this->tickets->count(), ['count' => $this->tickets->count()]) }}</span>
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

    {{-- The panes live INSIDE the slot: `tab` is Alpine state declared by the
    component, and a pane outside it has no scope to read. --}}
    <x-ui.tabs
        default="queue"
        :tabs="[
            ['value' => 'queue', 'label' => __('support.admin.tabs.queue')],
            ['value' => 'help', 'label' => __('support.admin.tabs.help'), 'badge' => $this->helpAttention > 0 ? $this->helpAttention : null],
        ]"
    >
        <div x-show="tab === 'queue'" wire:key="pane-queue">
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
                        name="filter"
                        :label="__('support.admin.status')"
                        :value="$filter"
                        :options="['open' => __('support.admin.filter_open'), 'answer' => __('support.admin.filter_answer'), 'waiting' => __('support.admin.filter_waiting'), 'resolved' => __('support.admin.filter_resolved'), 'all' => __('support.admin.filter_all')]"
                        wire:model.live="filter"
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
                </x-catalog.form-row>

                @if ($this->tickets->isEmpty())
                    @if ($this->isFiltered)
                        <x-ui.empty-state icon="search" :title="__('support.admin.no_match_title')" :body="__('support.admin.no_match_body')" compact />
                    @else
                        <x-ui.empty-state icon="life-buoy" :title="__('support.admin.empty_title')" :body="__('support.admin.empty_body')" compact />
                    @endif
                @else
                    <div class="pay-table-wrap">
                        <table class="pay-table sup-table">
                            <thead>
                                <tr>
                                    <th>{{ __('support.admin.columns.wait') }}</th>
                                    <th>{{ __('support.admin.columns.report') }}</th>
                                    <th>{{ __('support.admin.business') }}</th>
                                    <th>{{ __('support.admin.columns.about') }}</th>
                                    <th>{{ __('support.admin.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->tickets as $ticket)
                                    {{-- Over her limit the row is painted, not only tagged: a
                                    tag is read, a row is seen from the end of the list. --}}
                                    <tr wire:key="ticket-{{ $ticket->id }}" @class(['is-over' => $ticket->isOverdue()])>
                                        <td class="sup-wait font-mono" data-label="{{ __('support.admin.columns.wait') }}">
                                            @if ($ticket->waitLabel() !== null)
                                                <span class="aiu-cost-now">{{ $ticket->waitLabel() }}</span>
                                                @if ($ticket->isOverdue())
                                                    <span class="status-tag is-danger">{{ __('support.admin.overdue', ['hours' => config('atendia.support.overdue_hours')]) }}</span>
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
                                            <button type="button" class="sup-body text-left" wire:click="toggle({{ $ticket->id }})"
                                                data-testid="sup-expand-{{ $ticket->id }}" aria-expanded="{{ $expanded === $ticket->id ? 'true' : 'false' }}">
                                                {{ $ticket->excerpt() }}
                                            </button>
                                            <span class="sup-code">{{ $ticket->code }}</span>
                                        </td>

                                        <td data-label="{{ __('support.admin.business') }}">
                                            @if ($ticket->business_id !== null)
                                                <a class="row-link" wire:navigate href="{{ route('admin.businesses', ['negocio' => $ticket->business_id]) }}">{{ $ticket->business?->name }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>

                                        <td data-label="{{ __('support.admin.columns.about') }}">
                                            <span class="status-tag is-neutral">{{ __('support.kinds.'.$ticket->kind->value) }}</span>
                                            <span class="aiu-note">{{ $this->screenName($ticket->screen) }}</span>
                                        </td>

                                        <td class="sup-status" data-label="{{ __('support.admin.status') }}">
                                            <x-inputsform.combobox
                                                size="s"
                                                :name="'status-'.$ticket->id"
                                                :value="$ticket->status->value"
                                                :options="$this->statuses"
                                                :aria-label="__('support.admin.change_status', ['code' => $ticket->code])"
                                                wire:change="setStatus({{ $ticket->id }}, $event.target.value)"
                                            />
                                        </td>
                                    </tr>

                                    @if ($expanded === $ticket->id)
                                        <tr class="sup-detail" wire:key="detail-{{ $ticket->id }}">
                                            <td colspan="5">
                                                {{-- Only when the row above truncated it: printing the
                                                same line twice reads as a glitch. --}}
                                                @if ($ticket->excerpt() !== $ticket->body)
                                                    <p class="sup-body">{{ $ticket->body }}</p>
                                                @endif

                                                <p class="sup-meta">
                                                    @if ($ticket->user?->name !== null)
                                                        <span>{{ __('support.admin.opened_by', ['name' => $ticket->user->name]) }}</span>
                                                    @endif
                                                    <span>{{ $ticket->created_at?->format('d/m/Y H:i') }}</span>
                                                    @if ($ticket->attachment_path !== null)
                                                        <a href="{{ $this->fileUrl($ticket->attachment_path) }}" target="_blank" rel="noopener">{{ __('support.admin.attachment') }}</a>
                                                    @endif
                                                </p>

                                                {{-- What the widget captured, read like a person
                                                reads it: the raw JSON was evidence for a machine. --}}
                                                @php($evidence = $ticket->evidence())
                                                @unless ($evidence->isEmpty)
                                                    <p class="sup-meta">{{ __('support.admin.context') }}</p>
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
                                                @endunless

                                                @if ($ticket->reply !== null)
                                                    <p class="sup-meta">{{ __('support.admin.answered_at', ['when' => $ticket->answered_at?->diffForHumans()]) }}</p>
                                                    <p class="sup-body">{{ $ticket->reply }}</p>
                                                @else
                                                    {{-- The answer travels to her WhatsApp; a reply that
                                                    only lives here is one nobody reads. --}}
                                                    <x-catalog.form-row>
                                                        <x-inputsform.textarea
                                                            span="full"
                                                            :name="'reply-'.$ticket->id"
                                                            :label="__('support.admin.reply')"
                                                            :hint="__('support.admin.reply_hint')"
                                                            :rows="3"
                                                            maxlength="900"
                                                            wire:model="reply"
                                                        />
                                                    </x-catalog.form-row>
                                                    <span class="support-actions">
                                                        <x-ui.button variant="primary" size="sm" wire:click="answer({{ $ticket->id }})"
                                                            wire:loading.attr="disabled">
                                                            {{ __('support.admin.send_reply') }}
                                                        </x-ui.button>
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>

        {{-- The help is part of the same job (a failing article is tomorrow's
        ticket), but it is read once a week and the queue is read all day. --}}
        <div x-show="tab === 'help'" x-cloak wire:key="pane-help">
            @if ($this->deflection['opened'] === 0 && $this->staleArticles->isEmpty() && $this->failingArticles->isEmpty() && $this->painPoints->isEmpty())
                <x-ui.card class="p-5">
                    <x-ui.empty-state icon="life-buoy" :title="__('support.admin.help_empty_title')" :body="__('support.admin.help_empty_body')" compact />
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
        </div>
    </x-ui.tabs>
</div>
