<?php

use App\Actions\Support\AnswerSupportTicket;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
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
 */
new class extends Component
{
    use HasNotifications;

    #[Url(as: 'estado', except: 'open')]
    public string $filter = 'open';

    public ?int $expanded = null;

    /** @return Collection<int, SupportTicket> */
    #[Computed]
    public function tickets(): Collection
    {
        return SupportTicket::inbox($this->filter === 'open');
    }

    public function toggle(int $id): void
    {
        $this->expanded = $this->expanded === $id ? null : $id;
    }

    /** What the admin is typing, keyed by ticket: one open reply at a time. */
    public string $reply = '';

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

        unset($this->tickets, $this->painPoints);

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
        unset($this->tickets, $this->painPoints);

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
     * The answers that are not answering. Sits next to the tickets because it
     * is the same job: a failing article is tomorrow's ticket.
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

    /** @return array<string, string> */
    #[Computed]
    public function statuses(): array
    {
        return collect(SupportTicketStatus::cases())
            ->mapWithKeys(fn (SupportTicketStatus $case): array => [$case->value => $case->label()])
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
        was read (atendiadesign §7.1). --}}
        <span class="sup-age">{{ __('admin.home.as_of', ['date' => now()->format('d/m/Y H:i')]) }}</span>
    </x-ui.page-head>

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

    @if ($this->failingArticles->isNotEmpty())
        {{-- A failing article is tomorrow's ticket: it belongs here, not in a
        report nobody opens. --}}
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

    <x-ui.card class="p-5">
        <x-catalog.form-row>
            <x-inputsform.combobox
                span="short"
                name="filter"
                wire:model.live="filter"
                :value="$filter"
                :placeholder="__('support.admin.status')"
                :options="['open' => __('support.admin.filter_open'), 'all' => __('support.admin.filter_all')]"
            />
        </x-catalog.form-row>

        @forelse ($this->tickets as $ticket)
            <div class="sup-row" wire:key="ticket-{{ $ticket->id }}">
                <span class="sup-side">
                    <x-ui.badge variant="brand">{{ $ticket->status->label() }}</x-ui.badge>
                </span>

                <span class="sup-main">
                    <button type="button" class="sup-body text-left" wire:click="toggle({{ $ticket->id }})"
                        data-testid="sup-expand-{{ $ticket->id }}">
                        {{ $ticket->excerpt() }}
                    </button>
                    <span class="sup-meta">
                        <span class="sup-code">{{ $ticket->code }}</span>
                        @if ($ticket->business_id !== null)
                            <a class="row-link" wire:navigate href="{{ route('admin.businesses', ['negocio' => $ticket->business_id]) }}">{{ $ticket->business?->name }}</a>
                        @endif
                        <span>{{ __('support.kinds.'.$ticket->kind->value) }}</span>
                        <span>{{ $this->screenName($ticket->screen) }}</span>
                        <span class="sup-age">{{ $ticket->created_at?->diffForHumans() }}</span>
                    </span>

                    @if ($expanded === $ticket->id)
                        {{-- Only when the row above truncated it: printing the
                        same line twice reads as a glitch. --}}
                        @if ($ticket->excerpt() !== $ticket->body)
                            <span class="sup-body">{{ $ticket->body }}</span>
                        @endif
                        @if ($ticket->user?->name !== null)
                            <span class="sup-meta">{{ __('support.admin.opened_by', ['name' => $ticket->user->name]) }}</span>
                        @endif
                        @if ($ticket->attachment_path !== null)
                            <a class="sup-meta" href="{{ $this->fileUrl($ticket->attachment_path) }}" target="_blank" rel="noopener">
                                {{ __('support.admin.attachment') }}
                            </a>
                        @endif
                        @if ($ticket->reply !== null)
                            <span class="sup-meta">{{ __('support.admin.answered_at', ['when' => $ticket->answered_at?->diffForHumans()]) }}</span>
                            <span class="sup-body">{{ $ticket->reply }}</span>
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

                        {{-- Shown last and in mono: it is evidence, not copy. --}}
                        <span class="sup-context">{{ json_encode($ticket->context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</span>
                    @endif
                </span>

                <span class="sup-side">
                    <x-inputsform.combobox
                        span="short"
                        :name="'status-'.$ticket->id"
                        :value="$ticket->status->value"
                        :options="$this->statuses"
                        :aria-label="__('support.admin.change_status', ['code' => $ticket->code])"
                        wire:change="setStatus({{ $ticket->id }}, $event.target.value)"
                    />
                </span>
            </div>
        @empty
            <x-ui.empty-state icon="life-buoy" :title="__('support.admin.empty_title')" :body="__('support.admin.empty_body')" compact />
        @endforelse
    </x-ui.card>
</div>
