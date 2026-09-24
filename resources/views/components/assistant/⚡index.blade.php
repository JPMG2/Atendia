<?php

use App\Actions\Business\DeleteAssistantFaq;
use App\Actions\Business\DraftFaqSuggestions;
use App\Actions\Business\ReindexKnowledgeSource;
use App\Classes\Main\Client;
use App\Classes\Main\KnowledgeBase;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Jobs\NotifyUnansweredCustomers;
use App\Livewire\Forms\Client\AssistantFaqForm;
use App\Models\KnowledgeSuggestion;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * "Lo que sabe tu asistente" — the knowledge base, visible and teachable.
 * Automatic sources on top; below, the owner teaches Q&A by hand and every
 * answer lands in the same pgvector space the assistant already searches.
 */
new class extends Component
{
    use HasNotifications;

    public AssistantFaqForm $form;

    public bool $sheetOpen = false;

    private function knowledge(): ?KnowledgeBase
    {
        return Client::for(Auth::user())->knowledgeBase;
    }

    /** @return list<array{type: string, present: bool, chunks: int, indexed_at: ?\Illuminate\Support\Carbon}> */
    #[Computed]
    public function sources(): array
    {
        return $this->knowledge()?->sources ?? [];
    }

    /** @return Collection<int, \App\Models\KnowledgeDocument> */
    #[Computed]
    public function faqs(): Collection
    {
        return $this->knowledge()?->faqs ?? new Collection;
    }

    /** @return \Illuminate\Support\Collection<string, Collection<int, KnowledgeSuggestion>> */
    #[Computed]
    public function suggestions(): \Illuminate\Support\Collection
    {
        return $this->knowledge()?->suggestions ?? collect();
    }

    public function add(): void
    {
        $this->form->setup();
        $this->sheetOpen = true;
    }

    /** The sheet opens on the suggestion, the team's answer or the AI draft already in. */
    public function teachSuggestion(int $id): void
    {
        $suggestion = $this->knowledge()?->suggestion($id);

        if ($suggestion === null) {
            return;
        }

        $this->form->setupFromSuggestion($suggestion);
        $this->form->answer = $this->form->answer !== '' ? $this->form->answer : ($this->drafts[$suggestion->question] ?? '');
        $this->sheetOpen = true;
    }

    /**
     * One click teaches the draft as is. The sheet opens first so a draft
     * that fails validation shows its error instead of vanishing.
     */
    public function approve(int $id): void
    {
        $this->teachSuggestion($id);

        if ($this->sheetOpen) {
            $this->saveFaq();
        }
    }

    /** The dialog confirmed; its repeats keep folding in without coming back. */
    public function dismissSuggestion(int $id): void
    {
        $this->knowledge()?->suggestion($id)?->dismiss();

        $this->dispatchNotification(new NotificationDto(__('client.assistant.dismissed'), NotificationType::Success));
    }

    /** @var array<string, string> question => AI-drafted answer, this request's crop */
    public array $drafts = [];

    /**
     * Hunts near-miss knowledge for the questions nobody on the team
     * answered and drafts a grounded suggestion. Owner-triggered on purpose:
     * one model call per question, only when the queue is worth sweeping.
     */
    public function suggest(): void
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return;
        }

        $questions = $this->suggestions->flatten(1)
            ->filter(fn (KnowledgeSuggestion $suggestion): bool => $suggestion->teamAnswer === null)
            ->pluck('question')
            ->take(5)
            ->all();

        $this->drafts = app(DraftFaqSuggestions::class)->handle($business, $questions);

        if ($this->drafts === []) {
            $this->dispatchNotification(new NotificationDto(__('client.assistant.no_drafts'), NotificationType::Warning));
        }
    }

    public function edit(int $id): void
    {
        $faq = $this->knowledge()?->faq($id);

        if ($faq === null) {
            return;
        }

        $this->form->setup($faq);
        $this->sheetOpen = true;
    }

    public function closeSheet(): void
    {
        $this->sheetOpen = false;
    }

    public function saveFaq(): void
    {
        $suggestionId = $this->form->suggestionId;
        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Error) {
            $this->sheetOpen = false;
            $this->lastTaughtId = $suggestionId;
            $this->customersNotified = false;
        }
    }

    /** The suggestion just taught: the banner offers to try it and to tell who asked. */
    #[Locked]
    public ?int $lastTaughtId = null;

    #[Locked]
    public bool $customersNotified = false;

    #[Computed]
    public function lastTaught(): ?KnowledgeSuggestion
    {
        return $this->lastTaughtId === null ? null : $this->knowledge()?->taughtSuggestion($this->lastTaughtId);
    }

    public function closeTaught(): void
    {
        $this->lastTaughtId = null;
    }

    /** The dialog confirmed: the answer goes to whoever asked and got none. */
    public function notifyCustomers(): void
    {
        $suggestion = $this->lastTaught;

        if ($suggestion === null) {
            return;
        }

        NotifyUnansweredCustomers::dispatch($suggestion->business_id, $suggestion->id);
        $this->customersNotified = true;

        $this->dispatchNotification(new NotificationDto(__('client.assistant.customers_notified'), NotificationType::Success));
    }

    /**
     * Every draft the team already wrote, taught in one go. Each passes the
     * same validation as a single approval; one that fails stays queued.
     */
    public function approveTeamDrafts(): void
    {
        $taught = 0;

        foreach ($this->suggestions->flatten(1)->filter(fn (KnowledgeSuggestion $suggestion): bool => $suggestion->teamAnswer !== null) as $suggestion) {
            $this->form->setupFromSuggestion($suggestion);

            try {
                $taught += $this->form->save()->type !== NotificationType::Error ? 1 : 0;
            } catch (ValidationException) {
                continue;
            }
        }

        $this->form->setup();
        $this->lastTaughtId = null;
        unset($this->suggestions);

        $this->dispatchNotification(new NotificationDto(
            trans_choice('client.assistant.approved_many', $taught, ['count' => $taught]),
            $taught > 0 ? NotificationType::Success : NotificationType::Warning,
        ));
    }

    /** @var array{question: string, answer: string}|null */
    public ?array $tryResult = null;

    /**
     * One REAL pass through the assistant with the taught question: seeing
     * it answered kills any doubt that the teaching worked. Only offered
     * once the embedding is indexed — earlier it would honestly miss.
     */
    public function tryNow(int $id): void
    {
        $faq = $this->knowledge()?->faq($id);
        $business = Auth::user()?->business;

        if ($faq === null || $business === null) {
            return;
        }

        $answer = rescue(fn (): string => (new \App\Ai\Agents\AsistenteAtendia($business))->answer($faq->title)->text, report: false);

        $this->tryResult = [
            'question' => (string) $faq->title,
            'answer' => $answer ?? __('client.assistant.try_failed'),
        ];
    }

    public function closeTry(): void
    {
        $this->tryResult = null;
    }

    /** The dialog confirmed; the assistant forgets the answer with the row. */
    public function deleteFaq(int $id): void
    {
        $faq = $this->knowledge()?->faq($id);

        if ($faq === null) {
            return;
        }

        app(DeleteAssistantFaq::class)->handle($faq);

        $this->dispatchNotification(new NotificationDto(__('client.assistant.deleted'), NotificationType::Success));
    }

    public function reindex(string $type): void
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return;
        }

        app(ReindexKnowledgeSource::class)->handle($business, $type);

        $this->dispatchNotification(new NotificationDto(__('client.assistant.reindexed'), NotificationType::Success));
    }

    /** The tab title comes from translations; a PHP attribute cannot call __(). */
    public function render(): View
    {
        return $this->view()->title(__('client.assistant.title'));
    }
};
?>

<div>
    <div class="page-head">
        <div>
            <h1 class="page-head-title">{{ __('client.assistant.title') }}</h1>
            <p class="page-head-sub">{{ __('client.assistant.sub') }}</p>
        </div>
    </div>

    @if ($this->lastTaught !== null)
        <x-assistant.taught-banner :suggestion="$this->lastTaught" :notified="$customersNotified" />
    @endif

    {{-- The teaching queue on top, one list for everything the assistant
    did not know, by topic. Teaching an item takes it off by itself. --}}
    @if ($this->suggestions->isNotEmpty())
        <x-ui.card class="mb-4 p-5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-strong text-base">{{ __('client.assistant.suggestions_title') }}</h2>
                    <p class="text-muted mt-0.5 text-sm">{{ __('client.assistant.suggestions_sub') }}</p>
                </div>
                @php($teamDrafts = $this->suggestions->flatten(1)->whereNotNull('teamAnswer')->count())
                @if ($teamDrafts > 1)
                    <x-ui.button
                        variant="secondary"
                        size="sm"
                        icon="check-check"
                        x-on:click="dialog.confirm({
                            title: {{ \Illuminate\Support\Js::from(__('client.assistant.approve_all_confirm_title')) }},
                            message: {{ \Illuminate\Support\Js::from(trans_choice('client.assistant.approve_all_confirm_body', $teamDrafts, ['count' => $teamDrafts])) }},
                            accept: {{ \Illuminate\Support\Js::from(__('client.assistant.approve_all')) }},
                            type: 'info',
                        }).then((ok) => ok && $wire.approveTeamDrafts())"
                    >
                        {{ trans_choice('client.assistant.approve_all_count', $teamDrafts, ['count' => $teamDrafts]) }}
                    </x-ui.button>
                @endif
                @if ($this->suggestions->flatten(1)->contains(fn ($suggestion) => $suggestion->teamAnswer === null))
                    <x-ui.button variant="secondary" size="sm" icon="bot" class="data-loading:opacity-50" wire:click="suggest">
                        {{ __('client.assistant.suggest') }}
                    </x-ui.button>
                @endif
            </div>

            @foreach ($this->suggestions as $topic => $items)
                <div wire:key="topic-{{ $loop->index }}" class="mt-4">
                    <p class="eyebrow px-2">
                        {{ $topic }} · <span class="font-mono">{{ $items->sum('asked_count') }}</span>
                    </p>

                    <ul class="mt-1 divide-y divide-[color:var(--border-subtle)]">
                        @foreach ($items as $suggestion)
                            <x-assistant.suggestion-row
                                wire:key="suggestion-{{ $suggestion->id }}"
                                :suggestion="$suggestion"
                                :draft="$suggestion->teamAnswer?->answer ?? ($drafts[$suggestion->question] ?? null)"
                            />
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </x-ui.card>
    @endif

    {{-- The automatic feeds: transparency kills the black-box fear. --}}
    <x-ui.card class="p-5">
        <h2 class="font-display text-strong text-base">{{ __('client.assistant.sources_title') }}</h2>
        <p class="text-muted mt-0.5 text-sm">{{ __('client.assistant.sources_sub') }}</p>

        <ul class="mt-3 divide-y divide-[color:var(--border-subtle)]">
            @foreach ($this->sources as $source)
                @php($icon = ['profile' => 'store', 'services' => 'briefcase', 'products' => 'package', 'import' => 'layers'][$source['type']])
                <li wire:key="source-{{ $source['type'] }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-2 py-2.5">
                    <span class="bg-brand-soft flex size-9 flex-none items-center justify-center rounded-lg" style="color: var(--brand)">
                        <x-icon :name="$icon" :size="18" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="text-strong block text-sm font-semibold">{{ __('client.assistant.sources.'.$source['type']) }}</span>
                        <span class="text-muted block text-xs">
                            @if ($source['present'])
                                {{ trans_choice('client.assistant.indexed', $source['chunks'], ['chunks' => $source['chunks']]) }}
                                @if ($source['indexed_at'] !== null)
                                    · {{ $source['indexed_at']->diffForHumans() }}
                                @endif
                            @else
                                {{ __('client.assistant.source_empty') }}
                            @endif
                        </span>
                    </span>
                    @if ($source['present'])
                        <x-ui.icon-button
                            icon="refresh-cw"
                            size="sm"
                            variant="ghost"
                            :label="__('client.assistant.reindex')"
                            wire:click="reindex('{{ $source['type'] }}')"
                        />
                    @endif
                </li>
            @endforeach
        </ul>
    </x-ui.card>

    {{-- The teachable half: what the catalog cannot say. --}}
    <x-ui.card class="mt-4 p-5">
        <div class="flex flex-wrap items-center gap-3">
            <div class="min-w-0 flex-1">
                <h2 class="font-display text-strong text-base">{{ __('client.assistant.faq_title') }}</h2>
                <p class="text-muted mt-0.5 text-sm">{{ __('client.assistant.faq_sub') }}</p>
            </div>
            <x-ui.button variant="primary" size="sm" icon="plus" wire:click="add">
                {{ __('client.assistant.add') }}
            </x-ui.button>
        </div>

        @if ($this->faqs->isEmpty())
            <div class="mt-4 flex items-start gap-4">
                <div class="bg-brand-soft flex size-11 flex-none items-center justify-center rounded-xl" style="color: var(--brand)">
                    <x-icon name="sparkles" :size="22" />
                </div>
                <div class="min-w-0">
                    <h3 class="text-strong text-sm font-semibold">{{ __('client.assistant.faq_empty_title') }}</h3>
                    <p class="text-body mt-1 text-sm">{{ __('client.assistant.faq_empty_body') }}</p>
                </div>
            </div>
        @else
            <ul class="mt-3 divide-y divide-[color:var(--border-subtle)]">
                @foreach ($this->faqs as $faq)
                    <li wire:key="faq-{{ $faq->id }}" class="hover:bg-sunken flex flex-wrap items-center gap-x-4 gap-y-1 rounded-lg px-2 py-2.5 transition-colors">
                        <button type="button" wire:click="edit({{ $faq->id }})" class="min-w-0 flex-1 text-left">
                            <span class="text-strong block truncate text-sm font-semibold">{{ $faq->title }}</span>
                            <span class="text-muted block truncate text-xs">{{ str($faq->content)->after("Respuesta: ") }}</span>
                        </button>
                        <span class="text-subtle flex-none font-mono text-xs">
                            {{ $faq->indexed_at !== null ? __('client.assistant.learned') : __('client.assistant.learning') }}
                            @if (($faq->times_used ?? 0) > 0)
                                · {{ trans_choice('client.assistant.times_used', $faq->times_used, ['count' => $faq->times_used]) }}
                            @endif
                        </span>
                        @if ($faq->indexed_at !== null)
                            <x-ui.button variant="ghost" size="sm" icon="bot" class="data-loading:opacity-50" wire:click="tryNow({{ $faq->id }})">
                                {{ __('client.assistant.try') }}
                            </x-ui.button>
                        @endif
                        <x-ui.icon-button
                            icon="trash-2"
                            size="sm"
                            variant="ghost"
                            :label="__('client.assistant.delete')"
                            x-on:click="dialog.confirm({
                                title: {{ \Illuminate\Support\Js::from(__('client.assistant.delete_confirm_title')) }},
                                message: {{ \Illuminate\Support\Js::from(__('client.assistant.delete_confirm_body')) }},
                                accept: {{ \Illuminate\Support\Js::from(__('client.assistant.delete')) }},
                                type: 'danger',
                            }).then((ok) => ok && $wire.deleteFaq({{ $faq->id }}))"
                        />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- The live proof: the taught question answered by the REAL assistant. --}}
    @if ($tryResult !== null)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeTry()"
            :title="__('client.assistant.try_title')"
            :subtitle="__('client.assistant.try_hint')"
        >
            <div class="space-y-2 rounded-xl p-4" style="background: var(--chat-canvas)">
                <div class="pm-row in">
                    <div class="pm-bubble in">{{ $tryResult['question'] }}</div>
                </div>
                <div class="pm-row out">
                    <div class="pm-bubble out">{{ $tryResult['answer'] }}</div>
                </div>
            </div>

            <x-slot:footer>
                <span class="flex-1"></span>
                <x-ui.button variant="primary" size="sm" wire:click="closeTry">{{ __('client.assistant.try_close') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif

    <x-client.faq-sheet :form="$form" :show="$sheetOpen" />
</div>
