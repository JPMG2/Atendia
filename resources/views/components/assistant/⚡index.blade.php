<?php

use App\Actions\Business\DeleteAssistantFaq;
use App\Actions\Business\DraftFaqSuggestions;
use App\Actions\Business\ReindexKnowledgeSource;
use App\Actions\Business\SaveHandoffLevel;
use App\Enums\HandoffLevel;
use App\Classes\Main\Client;
use App\Classes\Main\KnowledgeBase;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\Client\AssistantFaqForm;
use App\Livewire\Forms\Client\HandoffRulesForm;
use App\Traits\HasNotifications;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
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

    public HandoffRulesForm $handoffForm;

    public bool $sheetOpen = false;

    /** The handoff dial: eager | balanced | minimal (the owner's insight). */
    public string $handoffLevel = 'balanced';

    public function mount(): void
    {
        $this->handoffLevel = (Auth::user()?->business?->handoff_level ?? HandoffLevel::Balanced)->value;
        $this->handoffForm->setup();
    }

    public function saveHandoffRules(): void
    {
        $this->dispatchNotification($this->handoffForm->save());
    }

    public function updatedHandoffLevel(string $value): void
    {
        $level = HandoffLevel::tryFrom($value);
        $business = Auth::user()?->business;

        if ($level === null || $business === null) {
            $this->handoffLevel = ($business?->handoff_level ?? HandoffLevel::Balanced)->value;

            return;
        }

        app(SaveHandoffLevel::class)->handle($business, $level);

        $this->dispatchNotification(new NotificationDto(__('client.assistant.handoff_saved'), NotificationType::Success));
    }

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

    /** @return list<array{question: string, count: int}> */
    #[Computed]
    public function misses(): array
    {
        return $this->knowledge()?->misses ?? [];
    }

    public function add(): void
    {
        $this->form->setup();
        $this->sheetOpen = true;
    }

    /** From the unanswered queue: the sheet opens with the question filled in. */
    public function teach(string $question): void
    {
        $this->form->setup();
        $this->form->question = $question;
        $this->sheetOpen = true;
    }

    /** @var array<string, string> question => AI-drafted answer, this request's crop */
    public array $drafts = [];

    /**
     * Hunts near-miss knowledge for every unanswered question and drafts a
     * grounded suggestion. Owner-triggered on purpose: at most five model
     * calls, and only when the queue is worth sweeping.
     */
    public function suggest(): void
    {
        $business = Auth::user()?->business;

        if ($business === null) {
            return;
        }

        $this->drafts = app(DraftFaqSuggestions::class)
            ->handle($business, array_column($this->misses, 'question'));

        if ($this->drafts === []) {
            $this->dispatchNotification(new NotificationDto(__('client.assistant.no_drafts'), NotificationType::Warning));
        }
    }

    /** The draft becomes the sheet's starting point; the owner edits and approves. */
    public function useDraft(string $question): void
    {
        $draft = $this->drafts[$question] ?? null;

        if ($draft === null) {
            return;
        }

        $this->form->setup();
        $this->form->question = $question;
        $this->form->answer = $draft;
        $this->sheetOpen = true;
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
        $notification = $this->form->save();
        $this->dispatchNotification($notification);

        if ($notification->type !== NotificationType::Error) {
            $this->sheetOpen = false;
        }
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

    {{-- The teaching queue on top: what customers asked and nobody could
    answer. Teaching it makes the same question stop appearing by itself. --}}
    @if ($this->misses !== [])
        <x-ui.card class="mb-4 p-5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="min-w-0 flex-1">
                    <h2 class="font-display text-strong text-base">{{ __('client.assistant.misses_title') }}</h2>
                    <p class="text-muted mt-0.5 text-sm">{{ __('client.assistant.misses_sub') }}</p>
                </div>
                <x-ui.button variant="secondary" size="sm" icon="bot" class="data-loading:opacity-50" wire:click="suggest">
                    {{ __('client.assistant.suggest') }}
                </x-ui.button>
            </div>

            <ul class="mt-3 divide-y divide-[color:var(--border-subtle)]">
                @foreach ($this->misses as $miss)
                    <li wire:key="miss-{{ $loop->index }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-2 py-2.5">
                        <span class="min-w-0 flex-1">
                            <span class="text-strong block truncate text-sm font-semibold">{{ $miss['question'] }}</span>
                            @if (isset($drafts[$miss['question']]))
                                <span class="text-muted mt-0.5 flex items-start gap-1.5 text-xs">
                                    <x-icon name="bot" :size="14" style="color: var(--brand)" class="mt-0.5 flex-none" />
                                    {{ $drafts[$miss['question']] }}
                                </span>
                            @endif
                        </span>
                        <span class="text-subtle flex-none font-mono text-xs">
                            {{ trans_choice('client.assistant.miss_count', $miss['count'], ['count' => $miss['count']]) }}
                        </span>
                        @if (isset($drafts[$miss['question']]))
                            <x-ui.button variant="primary" size="sm" icon="sparkles" wire:click="useDraft({{ \Illuminate\Support\Js::from($miss['question']) }})">
                                {{ __('client.assistant.use_draft') }}
                            </x-ui.button>
                        @else
                            <x-ui.button variant="secondary" size="sm" icon="sparkles" wire:click="teach({{ \Illuminate\Support\Js::from($miss['question']) }})">
                                {{ __('client.assistant.teach') }}
                            </x-ui.button>
                        @endif
                    </li>
                @endforeach
            </ul>
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
                                title: @js(__('client.assistant.delete_confirm_title')),
                                message: @js(__('client.assistant.delete_confirm_body')),
                                accept: @js(__('client.assistant.delete')),
                                type: 'danger',
                            }).then((ok) => ok && $wire.deleteFaq({{ $faq->id }}))"
                        />
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    {{-- The owner's dial: a doctor is not a realtor (her insight, 2026-09-20). --}}
    <x-ui.card class="mt-4 p-5">
        <h2 class="font-display text-strong text-base">{{ __('client.assistant.handoff_title') }}</h2>
        <p class="text-muted mt-0.5 text-sm">{{ __('client.assistant.handoff_sub') }}</p>

        <div class="mt-3 flex flex-col gap-4">
            <x-catalog.form-row>
                <x-inputsform.combobox
                    span="long"
                    name="handoff_level"
                    wire:model.live="handoffLevel"
                    :value="$handoffLevel"
                    :options="[
                        'eager' => __('client.assistant.handoff_levels.eager'),
                        'balanced' => __('client.assistant.handoff_levels.balanced'),
                        'minimal' => __('client.assistant.handoff_levels.minimal'),
                    ]"
                />
            </x-catalog.form-row>
            {{-- The owner's own cases, one per line; they ALWAYS escalate. --}}
            <x-catalog.form-row>
                <div class="f-full">
                    <x-ui.textarea
                        name="rules"
                        :rows="3"
                        :label="__('client.assistant.handoff_rules_label')"
                        :hint="__('client.assistant.handoff_rules_hint')"
                        :placeholder="__('client.assistant.handoff_rules_placeholder')"
                        wire:model="handoffForm.rules"
                    >{{ $handoffForm->rules }}</x-ui.textarea>
                </div>
            </x-catalog.form-row>
            <div class="flex justify-end">
                <x-ui.button variant="primary" size="sm" wire:click="saveHandoffRules">
                    {{ __('client.assistant.handoff_rules_save') }}
                </x-ui.button>
            </div>
        </div>
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

    @if ($sheetOpen)
        <x-ui.slide-over
            x-on:slide-over-close="$wire.closeSheet()"
            :title="$form->editingId !== null ? __('client.assistant.sheet_edit') : __('client.assistant.sheet_new')"
            :subtitle="__('client.assistant.sheet_hint')"
        >
            <div class="flex flex-col gap-4">
                <x-catalog.form-row>
                    <x-inputsform.input
                        span="full"
                        name="question"
                        :label="__('client.assistant.field_question')"
                        :placeholder="__('client.assistant.question_placeholder')"
                        wire:model="form.question"
                        :value="$form->question"
                    />
                </x-catalog.form-row>
                <x-catalog.form-row>
                    <div class="f-full">
                        <x-ui.textarea
                            name="answer"
                            :rows="4"
                            :label="__('client.assistant.field_answer')"
                            :hint="__('client.assistant.answer_hint')"
                            wire:model="form.answer"
                        >{{ $form->answer }}</x-ui.textarea>
                    </div>
                </x-catalog.form-row>
            </div>

            <x-slot:footer>
                <x-ui.button variant="danger" size="sm" wire:click="closeSheet">{{ __('client.assistant.sheet_cancel') }}</x-ui.button>
                <span class="flex-1"></span>
                <x-ui.button variant="primary" size="sm" wire:click="saveFaq">{{ __('client.assistant.sheet_save') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.slide-over>
    @endif
</div>
