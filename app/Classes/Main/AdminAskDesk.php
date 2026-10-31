<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Ai\Agents\AskAdmin;
use App\Ai\Tools\AdminAlerts;
use App\Ai\Tools\AdminEmailBusiness;
use App\Ai\Tools\AdminFindBusiness;
use App\Ai\Tools\AdminPaymentQueue;
use App\Ai\Tools\AdminPlatformPulse;
use App\Interfaces\Main\AdminSkillTool;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Contracts\ResolvesPendingApprovals;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;

/**
 * The admin's "Asistente IA" as data: who may ask, what is left of today's
 * cap, the day card, the suggestions and one answer. The web panel and the
 * mobile API both read it, so the phone cannot ask past a cap the panel closes.
 */
final class AdminAskDesk
{
    /** One message cannot carry a novel to the model. */
    public const int QUESTION_MAX = 500;

    /**
     * Each suggestion and the tool that answers it: one is offered only to whoever holds that tool's key,
     * so no button spends a question on "No tengo ese dato".
     *
     * @var array<string, class-string<AdminSkillTool>>
     */
    private const array SUGGESTIONS = [
        'pulse' => AdminPlatformPulse::class,
        'alerts' => AdminAlerts::class,
        'payments' => AdminPaymentQueue::class,
        'trouble' => AdminFindBusiness::class,
    ];

    /**
     * A suggestion turns sharper while what it asks about is waiting today, and only into a question a tool
     * answers: the queue lists the oldest receipt first, the alerts carry what to do with each.
     *
     * @var array<string, array{0: string, 1: string}> suggestion => [the day-card line that sharpens it, the sharper one]
     */
    private const array SHARPER = ['payments' => ['receipts', 'oldest_receipt'], 'alerts' => ['alerts', 'critical_alert']];

    /** Stored messages read back to redraw the thread: the agent's own memory of the conversation. */
    private const int THREAD_MESSAGES = 12;

    private ?int $left = null;

    private ?AdminAttention $card = null;

    public function __construct(public readonly User $admin) {}

    /** The lock is the key, not the button: a hidden button is only courtesy. */
    public bool $mayAsk {
        get => $this->admin->can('ai.ask');
    }

    public int $dailyCap {
        get => (int) config('atendia.admin_assistant.daily_questions');
    }

    /** A platform-wide ceiling of cost, counted from the usage the SDK listener records. */
    public int $questionsLeft {
        get => $this->left ??= max(0, $this->dailyCap - AiUsage::adminQuestionsToday());
    }

    public string $firstName {
        get => Str::before($this->admin->name, ' ');
    }

    public string $usedUp {
        get => __('ask_admin.used_up', ['cap' => $this->dailyCap]);
    }

    /** The day card, read once: the suggestions and the screens that print it share the same counts. */
    public AdminAttention $attention {
        get => $this->card ??= new AdminAttention($this->admin);
    }

    /** @var list<array{key: string, question: string}> */
    public array $suggestions {
        get {
            $waiting = array_column($this->attention->items, 'count', 'key');
            $agent = new AskAdmin($this->admin);
            $offered = array_filter(self::SUGGESTIONS, fn (string $tool): bool => $tool::forAdmin($agent) !== null);

            return array_values(array_map(function (string $key) use ($waiting): array {
                $sharper = self::SHARPER[$key] ?? null;

                if ($sharper !== null && isset($waiting[$sharper[0]])) {
                    // Agrees with the card above it: one alert, or the three it counts.
                    return ['key' => $sharper[1], 'question' => trans_choice('ask_admin.suggestions.'.$sharper[1], $waiting[$sharper[0]], ['count' => $waiting[$sharper[0]]])];
                }

                return ['key' => $key, 'question' => __('ask_admin.suggestions.'.$key)];
            }, array_keys($offered)));
        }
    }

    /**
     * The stored thread this panel was following, to draw it again after the panel closed or the screen
     * changed; nothing when the id is not this admin's. Only what was said: tool calls are the agent's own.
     *
     * @return list<array{role: 'owner'|'ai', text: string}>
     */
    public function thread(?string $conversationId): array
    {
        if (! (new AskAdmin($this->admin))->owns($conversationId)) {
            return [];
        }

        return resolve(ConversationStore::class)
            ->getLatestConversationMessages((string) $conversationId, self::THREAD_MESSAGES)
            ->filter(fn (Message $message): bool => in_array($message->role, [MessageRole::User, MessageRole::Assistant], true) && filled($message->content))
            ->map(fn (Message $message): array => ['role' => $message->role === MessageRole::User ? 'owner' : 'ai', 'text' => (string) $message->content])
            ->values()
            ->all();
    }

    /**
     * One pass of the agent, continuing the stored conversation only when it is
     * this admin's (`AskAdmin::resume` checks it). Null when it could not answer:
     * the cap is read again first, as a stale screen may still offer the box.
     * A run that paused on an e-mail comes back with its draft and maybe no text.
     *
     * @return array{text: string, conversation: string|null, draft: array<string, mixed>|null}|null
     */
    public function answer(string $question, ?string $conversationId): ?array
    {
        $this->left = null;

        if ($this->questionsLeft === 0) {
            return null;
        }

        $reply = rescue(function () use ($question, $conversationId): array {
            $response = (new AskAdmin($this->admin))->resume($conversationId)->answer($question);

            return [
                'text' => (string) $response->text,
                'conversation' => $response->conversationId ?? $conversationId,
                'draft' => $this->draftOf(collect($response->pendingApprovals)->first()),
            ];
        }, report: true);

        $this->left = null;

        return is_array($reply) && (filled($reply['text']) || $reply['draft'] !== null) ? $reply : null;
    }

    /**
     * The e-mail the stored conversation is waiting on, to draw its card again; null when it waits on nothing.
     *
     * @return array<string, mixed>|null
     */
    public function pendingDraft(?string $conversationId): ?array
    {
        $store = resolve(ConversationStore::class);

        if (! (new AskAdmin($this->admin))->owns($conversationId) || ! $store instanceof ResolvesPendingApprovals) {
            return null;
        }

        return $this->draftOf($store->pendingApprovalsFor((string) $conversationId)[0] ?? null);
    }

    /**
     * Her decision on the draft, and the run resumed with it: sent as she left
     * it (an edit when she corrected it) or not sent at all. Null when that
     * draft is no longer waiting (decided in another tab, by the other device
     * at the same moment, or not hers). `sent` is what the channel answered:
     * a send that did not leave says `failed`, never "Enviado".
     *
     * @return array{text: string, conversation: string, outcome: 'sent'|'dropped'|'failed', draft: array<string, mixed>|null}|null
     */
    public function decide(?string $conversationId, string $draftId, bool $send, string $subject = '', string $message = ''): ?array
    {
        // One decision per draft: the SDK runs the tool before it records the result, so two taps (or the web
        // and the phone at once) would both send. The second one waits for nothing and finds the draft gone.
        $lock = Cache::lock('ask-admin-draft:'.$conversationId.':'.$draftId, 120);

        if (! $lock->get()) {
            return null;
        }

        try {
            $draft = $this->pendingDraft($conversationId);

            if ($draft === null || $draft['id'] !== $draftId || ($send && $draft['recipients'] === 0)) {
                return null;
            }

            $decisions = $send
                // Anything else the model asked for in the same step does not go out with it: one letter at a time.
                ? Decisions::from([$draftId => $subject === $draft['subject'] && $message === $draft['message']
                    ? Decision::approve()
                    : Decision::edit([...$draft['arguments'], 'subject' => $subject, 'message' => $message]),
                ])->rejectRemaining(__('ask_admin.draft.rejected'))
                // A bare "no" ends the run there: the model is not called and no question is spent.
                : Decisions::from([$draftId => Decision::reject()])->rejectRemaining();

            AdminEmailBusiness::$sent = null;

            $reply = rescue(function () use ($conversationId, $decisions): array {
                $response = (new AskAdmin($this->admin))->resume($conversationId)->prompt($decisions);

                return ['text' => (string) $response->text, 'draft' => $this->draftOf(collect($response->pendingApprovals)->first())];
            }, report: true);

            $this->left = null;

            return [
                'text' => is_array($reply) ? $reply['text'] : '',
                'conversation' => (string) $conversationId,
                'outcome' => ! $send ? 'dropped' : (AdminEmailBusiness::$sent === true ? 'sent' : 'failed'),
                'draft' => is_array($reply) ? $reply['draft'] : null,
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * A paused call drawn as the card she decides on. Only the e-mail tool ever pauses; the business and
     * who receives it are read from the database, never from what the model wrote.
     *
     * @return array{id: string, business: string, recipients: int, to: string, subject: string, message: string, arguments: array<string, mixed>}|null
     */
    private function draftOf(?PendingApproval $pending): ?array
    {
        // Without the key to write to businesses (taken away while it waited) there is nothing for her to send.
        if ($pending === null || $pending->tool !== class_basename(AdminEmailBusiness::class) || ! $this->admin->can('businesses.manage')) {
            return null;
        }

        $to = AdminEmailBusiness::recipients((int) ($pending->arguments['business_id'] ?? 0));
        $name = $to['business']->name ?? '—';
        $count = count($to['emails'] ?? []);

        return [
            'id' => $pending->id,
            'business' => $name,
            'recipients' => $count,
            'to' => trans_choice('ask_admin.draft.recipients', $count, ['business' => $name, 'count' => $count]),
            'subject' => (string) ($pending->arguments['subject'] ?? ''),
            'message' => (string) ($pending->arguments['message'] ?? ''),
            'arguments' => $pending->arguments,
        ];
    }
}
