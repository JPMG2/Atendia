<?php

use App\Ai\Agents\AskAtendia;
use App\Classes\Main\Plan;
use App\Livewire\Forms\Client\AskAtendiaForm;
use App\Models\AskFeedback;
use App\Models\Business;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

    #[Computed]
    public function plan(): Plan
    {
        return $this->business?->plan() ?? Plan::named(null);
    }

    /** The cheapest tier that opens the feature: what the locked panel sells. */
    #[Computed]
    public function unlockingPlan(): ?Plan
    {
        return collect(Plan::ladder())->first(fn (Plan $tier): bool => $tier->allowsAsk);
    }

    #[Computed]
    public function questionsLeft(): int
    {
        return max(0, $this->plan->askPerMonth - ($this->business?->askQuestionsThisMonth() ?? 0));
    }

    #[Computed]
    public function firstName(): string
    {
        return Str::before(Auth::user()?->name ?? '', ' ');
    }

    /**
     * Picked by the business's local clock: a morning opens with last night,
     * a Monday with last week — the question most likely to be asked.
     *
     * @return list<array{key: string, question: string}>
     */
    #[Computed]
    public function suggestions(): array
    {
        $now = now($this->business?->localTimezone() ?? config('app.timezone'));

        return array_map(fn (string $key): array => ['key' => $key, 'question' => __('ask.suggestions.'.$key)], [
            $now->hour < 12 ? 'overnight' : 'today',
            'unresolved',
            'birthdays',
            $now->isMonday() ? 'last_week' : 'top_topic',
        ]);
    }

    /**
     * The "needs you today" card: counts with the screen that solves each,
     * only what is above zero.
     *
     * @return list<array{key: string, count: int, icon: string, url: string}>
     */
    #[Computed]
    public function attention(): array
    {
        $counts = $this->business?->attentionToday() ?? [];
        $doors = [
            'waiting' => ['message-circle', route('conversations')],
            'to_teach' => ['graduation-cap', route('assistant')],
            'birthdays' => ['cake', route('customers')],
            'conversations' => ['zap', route('conversations')],
        ];

        return collect($doors)
            ->filter(fn (array $door, string $key): bool => ($counts[$key] ?? 0) > 0)
            ->map(fn (array $door, string $key): array => ['key' => $key, 'count' => $counts[$key], 'icon' => $door[0], 'url' => $door[1]])
            ->values()
            ->all();
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
        return ($this->business?->quotaRenewsOn() ?? now()->startOfMonth()->addMonthNoOverflow())->format('d/m/Y');
    }

    /** The gate lives in the action: a hidden form is UX, the plan and the quota are the lock. */
    /** A suggestion or a chart's "Preguntar" passes its question; typing goes through the input. */
    public function ask(?string $suggested = null): void
    {
        abort_unless(Auth::user()?->can('access-client-app') && $this->business !== null, 403);

        if (! $this->plan->allowsAsk || $this->questionsLeft === 0) {
            return;
        }

        if ($suggested !== null) {
            $this->form->question = $suggested;
        }

        $question = $this->form->validatedQuestion();
        $history = $this->messages;

        $this->messages[] = ['role' => 'owner', 'text' => $question];
        $this->form->reset('question');

        $answer = rescue(
            fn (): string => (new AskAtendia($this->business, $this->firstName, $history))->answer($question),
            report: true,
        );

        $this->messages[] = filled($answer)
            ? ['role' => 'ai', 'text' => $answer, 'rating' => null]
            : ['role' => 'ai', 'text' => __('ask.failed'), 'failed' => true];
        unset($this->questionsLeft);
    }

    /** One thumb per answer, kept with its question: the thread itself is never stored. */
    public function rate(int $index, string $rating): void
    {
        abort_unless(Auth::user()?->can('access-client-app') && $this->business !== null, 403);

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

    /**
     * The answer's text escaped, with only its own links turned live: a link
     * the model wrote to anywhere but this app stays plain text.
     */
    public function answerHtml(string $text): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $html = e($text);

        $html = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', function (array $link) use ($host): string {
            $url = html_entity_decode($link[2]);

            return parse_url($url, PHP_URL_HOST) === $host
                ? '<a href="'.e($url).'" wire:navigate class="ask-link">'.$link[1].'</a>'
                : $link[1];
        }, $html) ?? $html;

        $html = preg_replace('/\*\*(.+?)\*\*/', '<b>$1</b>', $html) ?? $html;

        return nl2br($html);
    }
};
?>

<div
    class="ask-atendia"
    x-data="{
        open: false,
        pending: '',
        show() {
            this.open = true;
            const ready = $wire.opened ? Promise.resolve() : $wire.open();
            ready.then(() => this.$nextTick(() => document.getElementById('in-question')?.focus()));

            return ready;
        },
        scrollDown() {
            this.$nextTick(() => {
                const thread = document.querySelector('.ask-thread');
                thread?.scrollTo({ top: thread.scrollHeight, behavior: 'smooth' });
            });
        },
        send(suggested) {
            const question = (suggested ?? $wire.form.question ?? '').trim();
            if (this.pending !== '' || question === '') {
                return;
            }
            this.pending = question;
            this.scrollDown();
            (suggested === undefined ? $wire.ask() : $wire.ask(question)).finally(() => {
                this.pending = '';
                this.scrollDown();
            });
        },
    }"
    x-on:slide-over-close.window="
        if (open) {
            open = false;
            $wire.close();
        }
    "
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

                            @foreach ($messages as $index => $message)
                                <div
                                    wire:key="ask-message-{{ $index }}"
                                    @class(['ask-msg', 'ask-msg-owner' => $message['role'] === 'owner', 'ask-msg-ai' => $message['role'] !== 'owner'])
                                >
                                    @if ($message['role'] === 'owner')
                                        {{ $message['text'] }}
                                    @else
                                        {!! $this->answerHtml($message['text']) !!}
                                    @endif
                                </div>
                                @if ($message['role'] === 'ai' && array_key_exists('rating', $message))
                                    <div class="ask-rate" wire:key="ask-rate-{{ $index }}">
                                        @if ($message['rating'] === null)
                                            <x-ui.icon-button
                                                icon="thumbs-up"
                                                size="sm"
                                                variant="ghost"
                                                :label="__('ask.rate.up')"
                                                :title="__('ask.rate.up')"
                                                wire:click="rate({{ $index }}, 'up')"
                                            />
                                            <x-ui.icon-button
                                                icon="thumbs-down"
                                                size="sm"
                                                variant="ghost"
                                                :label="__('ask.rate.down')"
                                                :title="__('ask.rate.down')"
                                                wire:click="rate({{ $index }}, 'down')"
                                            />
                                        @else
                                            <span class="ask-rate-done">
                                                <x-icon
                                                    :name="$message['rating'] === 'up' ? 'thumbs-up' : 'thumbs-down'"
                                                    :size="14"
                                                />
                                                {{ __('ask.rate.thanks') }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            @endforeach

                            <template x-if="pending !== ''">
                                <div class="ask-thread-pending">
                                    <div class="ask-msg ask-msg-owner" x-text="pending"></div>
                                    <div class="ask-msg ask-msg-ai ask-thinking">
                                        <span class="ask-dots"><i></i><i></i><i></i></span>
                                        {{ __('ask.thinking') }}
                                    </div>
                                </div>
                            </template>

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
