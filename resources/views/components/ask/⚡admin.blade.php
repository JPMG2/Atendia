<?php

use App\Classes\Main\AdminAskDesk;
use App\Livewire\Forms\Admin\AskAdminDraftForm;
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
    /** The session key of the stored conversation this admin's panel is following. */
    private const string FOLLOWING = 'ask_admin.conversation';

    public AskAdminForm $form;

    public AskAdminDraftForm $draftForm;

    /** @var list<array{role: string, text: string, failed?: bool}> */
    public array $messages = [];

    /**
     * The e-mail the run paused on, waiting for her: drawn as a card until she decides.
     *
     * @var array{id: string, business: string, recipients: int, to: string, subject: string, message: string}|null
     */
    public ?array $draft = null;

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
        return $this->desk?->attention->links ?? [];
    }

    /**
     * Opening finds the thread it was following: the conversation id rides the session, so closing the
     * panel or moving to another screen does not lose it, and the words are read back from the SDK's store.
     */
    public function open(): void
    {
        abort_unless($this->allowed(), 403);

        $this->opened = true;

        if ($this->messages === []) {
            $this->conversationId = session(self::FOLLOWING);
            $this->messages = $this->desk->thread($this->conversationId);
            $this->showDraft($this->desk->pendingDraft($this->conversationId));
        }
    }

    /** Sends the draft as she left it: corrected, it goes as an edit of what the assistant wrote. */
    public function sendDraft(): void
    {
        abort_unless($this->allowed() && $this->draft !== null, 403);

        ['subject' => $subject, 'message' => $message] = $this->draftForm->validatedDraft();

        // Sending runs the model once more: with today's cap spent it waits for tomorrow; not sending is always free.
        if ($this->desk->questionsLeft === 0) {
            $this->messages[] = ['role' => 'ai', 'text' => $this->desk->usedUp, 'failed' => true];

            return;
        }

        $this->settleDraft($this->desk->decide($this->conversationId, $this->draft['id'], true, $subject, $message), $subject);
        unset($this->questionsLeft);
    }

    public function dropDraft(): void
    {
        abort_unless($this->allowed() && $this->draft !== null, 403);

        $this->settleDraft($this->desk->decide($this->conversationId, $this->draft['id'], false), $this->draft['subject']);
    }

    /** @param  array<string, mixed>|null  $draft */
    private function showDraft(?array $draft): void
    {
        $this->draft = $draft === null ? null : array_diff_key($draft, ['arguments' => true]);
        $this->draftForm->subject = $draft['subject'] ?? '';
        $this->draftForm->message = $draft['message'] ?? '';
    }

    /**
     * @param  array{text: string, outcome: string, draft: array<string, mixed>|null}|null  $decided
     * @param  string  $subject  The subject as it went (or would have gone), corrected or not.
     */
    private function settleDraft(?array $decided, string $subject): void
    {
        $draft = $this->draft;

        if ($decided === null) {
            // Decided elsewhere already, or gone: the card goes and says so, nothing is sent twice.
            $this->messages[] = ['role' => 'ai', 'text' => __('ask_admin.draft.gone'), 'failed' => true];
            $this->showDraft(null);

            return;
        }

        $this->messages[] = ['role' => 'note', 'text' => __('ask_admin.draft.'.$decided['outcome']).' · '.$subject.' · '.$draft['business']];

        if (filled($decided['text'])) {
            $this->messages[] = ['role' => 'ai', 'text' => $decided['text']];
        }

        $this->showDraft($decided['draft']);
    }

    public function close(): void
    {
        $this->opened = false;
    }

    /** Starts a clean thread: the old one stays stored, only this panel stops following it. */
    public function newChat(): void
    {
        $this->reset('messages', 'conversationId');
        $this->showDraft(null);
        $this->form->reset('question');
        session()->forget(self::FOLLOWING);
    }

    /** A suggestion passes its question; typing goes through the input. */
    public function ask(?string $suggested = null): void
    {
        abort_unless($this->allowed(), 403);

        // A run paused on an e-mail is decided first: a new question would silently drop the draft.
        if ($this->draft !== null) {
            $this->messages[] = ['role' => 'ai', 'text' => __('ask_admin.draft.waiting'), 'failed' => true];

            return;
        }

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
        session([self::FOLLOWING => $this->conversationId]);

        if ($reply === null) {
            $this->messages[] = ['role' => 'ai', 'text' => __('ask_admin.failed'), 'failed' => true];
        } else {
            // A run that paused on an e-mail may say nothing yet: the draft card is its answer.
            if (filled($reply['text'])) {
                $this->messages[] = ['role' => 'ai', 'text' => $reply['text']];
            }

            $this->showDraft($reply['draft']);
        }

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

                            {{-- The e-mail the run paused on: nothing leaves until she decides here. --}}
                            @if ($draft !== null)
                                <div class="ask-draft" data-testid="ask-admin-draft" wire:key="ask-draft-{{ $draft['id'] }}">
                                    <p class="ask-draft-title"><x-icon name="mail" :size="16" /> {{ __('ask_admin.draft.title') }}</p>
                                    <p class="ask-draft-hint">{{ __('ask_admin.draft.hint') }}</p>
                                    <p class="ask-draft-to"><span>{{ __('ask_admin.draft.to') }}:</span> {{ $draft['to'] }}</p>
                                    <x-ui.input name="subject" :label="__('ask_admin.draft.subject')" wire:model="draftForm.subject" maxlength="120" />
                                    <x-ui.textarea name="message" :label="__('ask_admin.draft.message')" wire:model="draftForm.message" :rows="5" maxlength="2000" />
                                    {{-- Resuming the run takes the model a few seconds: say so instead of a frozen card. --}}
                                    <p class="ask-thinking" wire:loading wire:target="sendDraft,dropDraft">
                                        <span class="ask-dots" aria-hidden="true"><i></i><i></i><i></i></span>
                                        {{ __('ask_admin.thinking') }}
                                    </p>
                                    <div class="ask-draft-actions">
                                        <x-ui.button variant="danger" size="sm" wire:click="dropDraft" wire:loading.attr="disabled">{{ __('ask_admin.draft.drop') }}</x-ui.button>
                                        @if ($draft['recipients'] > 0)
                                            <x-ui.button variant="primary" size="sm" icon="send" wire:click="sendDraft" wire:loading.attr="disabled">{{ __('ask_admin.draft.send') }}</x-ui.button>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            {{-- Only the questions a tool of this person answers: with none, no title over an empty row.
                            While a draft waits there is nothing to ask: she decides it first. --}}
                            @if ($this->suggestions !== [] && $draft === null)
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
                            @endif
                        </div>

                        <x-slot:footer>
                            @if ($draft !== null)
                                <p class="ask-left">{{ __('ask_admin.draft.waiting') }}</p>
                            @elseif ($this->questionsLeft > 0)
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
