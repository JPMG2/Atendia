<?php

use App\Classes\Main\AnswerHtml;
use App\Classes\Main\AskDesk;
use App\Classes\Main\Plan;
use App\Livewire\Forms\Client\AskAtendiaForm;
use App\Models\AskFeedback;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Ask AtendIa": the topbar button and the side panel where the owner asks
 * about their own business. Read-only answers from the AskAtendia agent,
 * capped per plan; the thread lives in this component, never in the database.
 */
new class extends Component
{
    public AskAtendiaForm $form;

    /** @var list<array{role: string, text: string, rating?: ?string, failed?: bool}> */
    public array $messages = [];

    /** True while the panel is open: it is drawn (and the day card read) only then, never on every page the topbar rides. */
    public bool $opened = false;

    #[Computed]
    public function business(): ?Business
    {
        return Auth::user()?->business;
    }

    /** The same piece the app reads: quota, card, suggestions and answer cannot differ between the two. */
    #[Computed]
    public function desk(): AskDesk
    {
        return new AskDesk($this->business, Auth::user());
    }

    #[Computed]
    public function plan(): Plan
    {
        return $this->desk->plan;
    }

    #[Computed]
    public function unlockingPlan(): ?Plan
    {
        return $this->desk->unlockingPlan;
    }

    #[Computed]
    public function questionsLeft(): int
    {
        return $this->desk->questionsLeft;
    }

    #[Computed]
    public function firstName(): string
    {
        return $this->desk->firstName;
    }

    /** @return list<array{key: string, question: string}> */
    #[Computed]
    public function suggestions(): array
    {
        return $this->desk->suggestions;
    }

    /** @return list<array{key: string, count: int, icon: string, route: string, url: string}> */
    #[Computed]
    public function attention(): array
    {
        return array_map(fn (array $item): array => [...$item, 'url' => route($item['route'])], $this->desk->attention);
    }

    public function open(): void
    {
        $this->opened = true;
    }

    public function close(): void
    {
        $this->opened = false;
    }

    #[Computed]
    public function renewsOn(): string
    {
        return $this->desk->renewsOn;
    }

    /** The gate lives in the action: a hidden form is UX, the plan and the quota are the lock. */
    /** A suggestion or a chart's "Preguntar" passes its question; typing goes through the input. */
    public function ask(?string $suggested = null): void
    {
        // manage-business, not access-client-app: an invited agent has the
        // second one, and the panel already answers 403 to the screens this
        // assistant would read out loud — while spending the business's quota.
        abort_unless($this->desk->mayAsk, 403);

        if (! $this->desk->canAsk) {
            return;
        }

        if ($suggested !== null) {
            $this->form->question = $suggested;
        }

        $question = $this->form->validatedQuestion();
        $history = $this->messages;

        $this->messages[] = ['role' => 'owner', 'text' => $question];
        $this->form->reset('question');

        $answer = $this->desk->answer($question, $history);

        $this->messages[] = $answer !== null
            ? ['role' => 'ai', 'text' => $answer, 'rating' => null]
            : ['role' => 'ai', 'text' => __('ask.failed'), 'failed' => true];
        unset($this->desk, $this->questionsLeft);
    }

    /** One thumb per answer, kept with its question: the thread itself is never stored. */
    public function rate(int $index, string $rating): void
    {
        abort_unless($this->desk->mayAsk, 403);

        $answer = $this->messages[$index] ?? null;
        $question = $this->messages[$index - 1] ?? null;

        if (! in_array($rating, [AskFeedback::UP, AskFeedback::DOWN], true)
            || ($answer['role'] ?? null) !== 'ai'
            || ! array_key_exists('rating', $answer)
            || $answer['rating'] !== null
            || ($question['role'] ?? null) !== 'owner') {
            return;
        }

        AskFeedback::record($this->business, Auth::id(), $question['text'], $answer['text'], $rating);

        $this->messages[$index]['rating'] = $rating;
    }

    /** The intro with its name lit up: "asistente IA de Atendia" in bold jade, "IA" as the brand pill. */
    public function introHtml(): string
    {
        $pill = '<span class="ask-hello-ia">'.e(__('ask.ia')).'</span>';
        $name = str_replace('%IA%', $pill, e(__('ask.assistant', ['ia' => '%IA%'])));

        return str_replace('%ASSISTANT%', '<strong class="ask-hello-brand">'.$name.'</strong>', e(__('ask.intro', ['assistant' => '%ASSISTANT%'])));
    }

    /** The answer made safe to print: the one shared by both assistants. */
    public function answerHtml(string $text): string
    {
        return AnswerHtml::of($text);
    }
};
?>

<div
    class="ask-atendia"
    x-data="askPanel"
    x-on:slide-over-close.window="close()"
    x-on:ask-atendia.window="
        const question = $event.detail.question;
        show().then(() => {
            if ($root.dataset.canAsk === '1') {
                send(question);
            }
        });
    "
    data-can-ask="{{ $this->plan->allowsAsk && $this->questionsLeft > 0 ? '1' : '0' }}"
>
    {{-- Same markup as its neighbours (theme, bell): one row, one button style. --}}
    <button
        type="button"
        class="icon-btn icon-btn-secondary topbar-ask"
        data-testid="ask-atendia"
        aria-label="{{ __('ask.button') }}"
        title="{{ __('ask.button') }}"
        x-on:click="show()"
    >
        <x-icon name="sparkles" :size="20" />
        @unless ($this->plan->allowsAsk)
            <span class="topbar-ask-lock"><x-icon name="lock" :size="9" /></span>
        @endunless
    </button>

    {{-- To the body: the topbar is a containing block and would clip a fixed panel to its height. --}}
    @teleport('body')
        {{-- Drawn only while open: a hidden second panel would sit in every page's DOM. --}}
        <div x-show="open" x-cloak>
            @if ($opened)
                <x-ui.slide-over :title="__('ask.title')" :subtitle="__('ask.subtitle')">
                    @if ($this->plan->allowsAsk)
                        <div class="ask-thread" x-on:click="if ($event.target.closest('a.ask-link')) open = false;">
                            <div class="ask-hello">
                                <span class="ask-hello-avatar"><x-icon name="sparkles" :size="22" /></span>
                                <div class="min-w-0">
                                    <p class="ask-hello-title">{{ __('ask.hello', ['name' => $this->firstName]) }}</p>
                                    <p class="ask-hello-text">{!! $this->introHtml() !!}</p>
                                </div>
                            </div>

                            @if ($opened)
                                <div class="ask-today">
                                    <p class="ask-today-title">
                                        <x-icon name="bell" :size="16" />
                                        {{ __('ask.today.title') }}
                                    </p>
                                    @forelse ($this->attention as $item)
                                        <a
                                            href="{{ $item['url'] }}"
                                            wire:navigate
                                            wire:key="today-{{ $item['key'] }}"
                                            class="ask-today-row"
                                        >
                                            <x-icon :name="$item['icon']" :size="16" />
                                            <span>{{ trans_choice('ask.today.'.$item['key'], $item['count'], ['count' => $item['count']]) }}</span>
                                            <x-icon name="chevron-right" :size="16" class="ask-today-go" />
                                        </a>
                                    @empty
                                        <p class="ask-today-clear">{{ __('ask.today.clear') }}</p>
                                    @endforelse
                                </div>
                            @endif

                            <x-ask.messages :messages="$messages" :rateable="true" />

                            <x-ask.pending :thinking="__('ask.thinking')" />

                            <div class="ask-suggestions" x-show="pending === ''">
                                <p class="ask-suggestions-title">{{ __('ask.suggestions_title') }}</p>
                                @foreach ($this->suggestions as $suggestion)
                                    <button
                                        type="button"
                                        class="ask-chip"
                                        wire:key="ask-{{ $suggestion['key'] }}"
                                        x-on:click="send(@js($suggestion['question']))"
                                        @disabled($this->questionsLeft === 0)
                                    >
                                        <x-icon name="sparkles" :size="14" />
                                        {{ $suggestion['question'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="ask-locked">
                            <span class="ask-locked-icon"><x-icon name="sparkles" :size="24" /></span>
                            <h3 class="ask-locked-title">{{ __('ask.locked.title') }}</h3>
                            <p class="text-body text-sm">{{ __('ask.locked.body') }}</p>
                            @if ($this->unlockingPlan !== null)
                                <p class="ask-locked-plan">
                                    <x-icon name="lock" :size="14" />
                                    {{ __('ask.locked.plan', ['plan' => __('plan.names.'.$this->unlockingPlan->code), 'cap' => $this->unlockingPlan->askPerMonth]) }}
                                </p>
                            @endif

                            <p class="ask-suggestions-title">{{ __('ask.locked.examples') }}</p>
                            <ul class="ask-examples">
                                @foreach ($this->suggestions as $suggestion)
                                    <li wire:key="example-{{ $suggestion['key'] }}">{{ $suggestion['question'] }}</li>
                                @endforeach
                            </ul>

                            <x-ui.button
                                variant="primary"
                                size="md"
                                :href="route('my-plan')"
                                wire:navigate
                                :fullWidth="true"
                            >
                                {{ __('ask.locked.cta') }}
                            </x-ui.button>
                        </div>
                    @endif

                    @if ($this->plan->allowsAsk)
                        <x-slot:footer>
                            @if ($this->questionsLeft > 0)
                                <form class="ask-form" x-on:submit.prevent="send()">
                                    <div class="ask-form-row">
                                        <x-ui.input
                                            name="question"
                                            wire:model="form.question"
                                            :placeholder="__('ask.placeholder')"
                                            maxlength="500"
                                            autocomplete="off"
                                        />
                                        <x-ui.icon-button
                                            icon="send"
                                            size="md"
                                            variant="secondary"
                                            :label="__('ask.send')"
                                            x-on:click="send()"
                                        />
                                    </div>
                                    <x-plan.ask-quota
                                        size="compact"
                                        :used="$this->plan->askPerMonth - $this->questionsLeft"
                                        :cap="$this->plan->askPerMonth"
                                        :renewsOn="$this->renewsOn"
                                    />
                                </form>
                            @else
                                <p class="ask-left">
                                    {{ __('ask.used_up', ['cap' => $this->plan->askPerMonth, 'date' => $this->renewsOn]) }}
                                </p>
                            @endif
                        </x-slot:footer>
                    @endif
                </x-ui.slide-over>
            @endif
        </div>
    @endteleport
</div>
