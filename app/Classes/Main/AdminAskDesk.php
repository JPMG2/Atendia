<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Ai\Agents\AskAdmin;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * The admin's "Asistente IA" as data: who may ask, what is left of today's
 * cap, the day card, the suggestions and one answer. The web panel and the
 * mobile API both read it, so the phone cannot ask past a cap the panel closes.
 */
final class AdminAskDesk
{
    /** One message cannot carry a novel to the model. */
    public const int QUESTION_MAX = 500;

    private const array SUGGESTIONS = ['pulse', 'alerts', 'payments', 'trouble'];

    private ?int $left = null;

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

    /** @var list<array{key: string, question: string}> */
    public array $suggestions {
        get => array_map(fn (string $key): array => ['key' => $key, 'question' => __('ask_admin.suggestions.'.$key)], self::SUGGESTIONS);
    }

    /**
     * One pass of the agent, continuing the stored conversation only when it is
     * this admin's (`AskAdmin::resume` checks it). Null when it could not answer:
     * the cap is read again first, as a stale screen may still offer the box.
     *
     * @return array{text: string, conversation: string|null}|null
     */
    public function answer(string $question, ?string $conversationId): ?array
    {
        $this->left = null;

        if ($this->questionsLeft === 0) {
            return null;
        }

        $reply = rescue(function () use ($question, $conversationId): array {
            $response = (new AskAdmin($this->admin))->resume($conversationId)->answer($question);

            return ['text' => $response->text, 'conversation' => $response->conversationId ?? $conversationId];
        }, report: true);

        $this->left = null;

        return is_array($reply) && filled($reply['text']) ? $reply : null;
    }
}
