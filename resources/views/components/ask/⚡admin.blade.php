<?php

use App\Classes\Main\AdminAskDesk;
use App\Classes\Main\AdminAttention;
use App\Livewire\Forms\Admin\AskAdminForm;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The admin's AI assistant: the topbar button and the side panel where she asks
 * about the platform, its businesses and every screen of both panels. The cap,
 * the words and the answer come from `AdminAskDesk`, the piece the phone reads
 * too; this component only remembers which stored conversation it is in.
 */
new class extends Component
{
    public AskAdminForm $form;

    /** @var list<array{role: string, text: string, failed?: bool}> */
    public array $messages = [];

    /** The SDK's conversation this panel continues, null until the first answer is stored. */
    public ?string $conversationId = null;

    /** True while the panel is open: it is drawn only then, never on every page the topbar rides. */
    public bool $opened = false;

    #[Computed]
    public function desk(): ?AdminAskDesk
    {
        $user = Auth::user();

        return $user === null ? null : new AdminAskDesk($user);
    }

    /** The lock is the key, not the button: a hidden button is only courtesy. */
    public function allowed(): bool
    {
        return $this->desk?->mayAsk === true;
    }

    #[Computed]
    public function dailyCap(): int
    {
        return $this->desk?->dailyCap ?? 0;
    }

    #[Computed]
    public function questionsLeft(): int
    {
        return $this->desk?->questionsLeft ?? 0;
    }

    #[Computed]
    public function firstName(): string
    {
        return $this->desk?->firstName ?? '';
    }

    /** @return list<array{key: string, question: string}> */
    #[Computed]
    public function suggestions(): array
    {
        return $this->desk?->suggestions ?? [];
    }

    /**
     * The "needs you today" card: counts with the screen that solves each,
     * from the tables and not from the model, so it costs no question.
     *
     * @return list<array{key: string, count: int, icon: string, url: string}>
     */
    #[Computed]
    public function attention(): array
    {
        return (new AdminAttention(Auth::user()))->links;
    }

    public function open(): void
    {
        abort_unless($this->allowed(), 403);

        $this->opened = true;
    }

    public function close(): void
    {
        $this->opened = false;
    }

    /** Starts a clean thread: the old one stays stored, only this panel stops following it. */
    public function newChat(): void
    {
        $this->reset('messages', 'conversationId');
        $this->form->reset('question');
    }

    /** A suggestion passes its question; typing goes through the input. */
    public function ask(?string $suggested = null): void
    {
        abort_unless($this->allowed(), 403);

        // The panel may be a stale tab that still offers the input: say it, never swallow the question.
        if ($this->questionsLeft === 0) {
            $this->messages[] = ['role' => 'ai', 'text' => $this->desk->usedUp, 'failed' => true];

            return;
        }

        if ($suggested !== null) {
            $this->form->question = $suggested;
        }

        $question = $this->form->validatedQuestion();

        $this->messages[] = ['role' => 'owner', 'text' => $question];
        $this->form->reset('question');

        $reply = $this->desk->answer($question, $this->conversationId);
        $this->conversationId = $reply['conversation'] ?? $this->conversationId;

        $this->messages[] = $reply !== null
            ? ['role' => 'ai', 'text' => $reply['text']]
            : ['role' => 'ai', 'text' => __('ask_admin.failed'), 'failed' => true];
        unset($this->questionsLeft);
    }
};
?>

{{-- A "Preguntar" button anywhere in the admin panel says the question; this panel opens and asks it. --}}
<div
    class="ask-atendia"
    x-data="askPanel"
    x-on:slide-over-close.window="close()"
    x-on:ask-admin.window="show().then(() => send($event.detail.question))"
>
    @if ($this->allowed())
        <button
            type="button"
            class="icon-btn icon-btn-secondary topbar-ask"
            data-testid="ask-admin"
            aria-label="{{ __('ask_admin.button') }}"
            title="{{ __('ask_admin.button') }}"
            x-on:click="show()"
        >
            <x-icon name="sparkles" :size="20" />
        </button>

        {{-- To the body: the topbar is a containing block and would clip a fixed panel to its height. --}}
        @teleport('body')
            <div x-show="open" x-cloak>
                @if ($opened)
                    <x-ui.slide-over :title="__('ask_admin.title')" :subtitle="__('ask_admin.subtitle')">
                        <div class="ask-thread" x-on:click="if ($event.target.closest('a.ask-link')) open = false;">
                            <div class="ask-hello">
                                <span class="ask-hello-avatar"><x-icon name="sparkles" :size="22" /></span>
                                <div class="min-w-0">
                                    <p class="ask-hello-title">{{ __('ask_admin.hello', ['name' => $this->firstName]) }}</p>
                                    <p class="ask-hello-text">{{ __('ask_admin.intro', ['brand' => Company::brand()]) }}</p>
                                </div>
                            </div>

                            <x-ask.today :items="$this->attention" data-testid="ask-admin-today" />

                            <x-ask.messages :messages="$messages" />

                            <x-ask.pending :thinking="__('ask_admin.thinking')" />

                            <div class="ask-suggestions" x-show="pending === ''">
                                <p class="ask-suggestions-title">{{ __('ask_admin.suggestions_title') }}</p>
                                @foreach ($this->suggestions as $suggestion)
                                    <button
                                        type="button"
                                        class="ask-chip"
                                        wire:key="ask-admin-{{ $suggestion['key'] }}"
                                        x-on:click="send(@js($suggestion['question']))"
                                        @disabled($this->questionsLeft === 0)
                                    >
                                        <x-icon name="sparkles" :size="14" />
                                        {{ $suggestion['question'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <x-slot:footer>
                            @if ($this->questionsLeft > 0)
                                <form class="ask-form" x-on:submit.prevent="send()">
                                    <div class="ask-form-row">
                                        <x-ui.input
                                            name="question"
                                            wire:model="form.question"
                                            :placeholder="__('ask_admin.placeholder')"
                                            maxlength="500"
                                            autocomplete="off"
                                        />
                                        <x-ui.icon-button
                                            icon="send"
                                            size="md"
                                            variant="secondary"
                                            :label="__('ask_admin.send')"
                                            x-on:click="send()"
                                        />
                                    </div>
                                    <p class="ask-left">
                                        {{ trans_choice('ask_admin.left', $this->questionsLeft, ['count' => $this->questionsLeft]) }}
                                        @if ($messages !== [])
                                            ·
                                            <button type="button" class="row-link" wire:click="newChat">{{ __('ask_admin.new_chat') }}</button>
                                        @endif
                                    </p>
                                </form>
                            @else
                                <p class="ask-left">{{ __('ask_admin.used_up', ['cap' => $this->dailyCap]) }}</p>
                            @endif
                        </x-slot:footer>
                    </x-ui.slide-over>
                @endif
            </div>
        @endteleport
    @endif
</div>
