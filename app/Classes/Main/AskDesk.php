<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Ai\Agents\AskAtendia;
use App\Models\AskFeedback;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * "Pregúntale" as data: who may ask, what is left of the month, the day card,
 * the suggestions and the answer. The web panel and the mobile API both read
 * it, so the phone cannot ask past a quota the panel would close.
 */
final class AskDesk
{
    /** One message cannot carry a novel to the model. */
    public const int QUESTION_MAX = 500;

    /** An answer stays on the server this long, to be rated and to be handed back as the thread. */
    private const int KEPT_HOURS = 24;

    /** Questions and answers handed back to the model: the agent's own memory of ten turns. */
    private const int THREAD_PAIRS = 5;

    /** Longer than one capped call (40 s), so a second question waits for the first one's count. */
    private const int BUSY_SECONDS = 60;

    /** @var array<string, array{0: string, 1: string}> each day-card row: its icon and the screen that solves it */
    private const array DOORS = [
        'waiting' => ['message-circle', 'conversations'],
        'appointments' => ['calendar-check', 'agenda'],
        'to_teach' => ['graduation-cap', 'assistant'],
        'birthdays' => ['cake', 'customers'],
        'conversations' => ['zap', 'conversations'],
    ];

    private ?Plan $tier = null;

    private ?int $left = null;

    /** @var list<array{key: string, count: int, icon: string, route: string}>|null */
    private ?array $today = null;

    /** True when another question of this business was still being answered: this one never left. */
    public private(set) bool $busy = false;

    public function __construct(public readonly ?Business $business, public readonly ?User $owner) {}

    public Plan $plan {
        get => $this->tier ??= $this->business?->plan() ?? Plan::named(null);
    }

    /** Only the owner asks: an invited agent would spend the business's quota reading screens it may not open. */
    public bool $mayAsk {
        get => $this->business !== null && $this->owner?->can('manage-business') === true;
    }

    /** The cheapest tier that opens it: what the locked panel sells. */
    public ?Plan $unlockingPlan {
        get => collect(Plan::ladder())->first(fn (Plan $tier): bool => $tier->allowsAsk);
    }

    public int $questionsLeft {
        get => $this->left ??= max(0, $this->plan->askPerMonth - ($this->business?->askQuestionsThisMonth() ?? 0));
    }

    public bool $canAsk {
        get => $this->plan->allowsAsk && $this->questionsLeft > 0;
    }

    public string $firstName {
        get => Str::before($this->owner?->name ?? '', ' ');
    }

    public string $renewsOn {
        get => ($this->business?->quotaRenewsOn() ?? now()->startOfMonth()->addMonthNoOverflow())->format('d/m/Y');
    }

    /**
     * Picked by the business's local clock: a morning opens with last night,
     * a Monday with last week — the question most likely to be asked.
     *
     * @var list<array{key: string, question: string}>
     */
    public array $suggestions {
        get {
            $now = now($this->business?->localTimezone() ?? config('app.timezone'));

            return array_map(fn (string $key): array => ['key' => $key, 'question' => __('ask.suggestions.'.$key)], [
                $now->hour < 12 ? 'overnight' : 'today',
                'unresolved',
                'birthdays',
                $now->isMonday() ? 'last_week' : 'top_topic',
            ]);
        }
    }

    /**
     * The "needs you today" card: only what is above zero, each with the
     * route of the screen that solves it.
     *
     * @var list<array{key: string, count: int, icon: string, route: string}>
     */
    public array $attention {
        get {
            $counts = $this->today === null ? ($this->business?->attentionToday() ?? []) : [];

            return $this->today ??= collect(self::DOORS)
                ->filter(fn (array $door, string $key): bool => ($counts[$key] ?? 0) > 0)
                ->map(fn (array $door, string $key): array => ['key' => $key, 'count' => $counts[$key], 'icon' => $door[0], 'route' => $door[1]])
                ->values()
                ->all();
        }
    }

    /**
     * One capped pass of the owner's assistant; null when it could not answer.
     * One question per business at a time, and the quota counted again once
     * inside: two at once would both pass a check made before either spent.
     *
     * @param  list<array{role: string, text: string}>  $history
     */
    public function answer(string $question, array $history): ?string
    {
        $lock = Cache::lock('ask-busy:'.$this->business?->id, self::BUSY_SECONDS);

        if (! $lock->get()) {
            $this->busy = true;

            return null;
        }

        try {
            $this->left = null;

            if (! $this->canAsk) {
                return null;
            }

            $answer = rescue(
                fn (): string => (new AskAtendia($this->business, $this->firstName, $history))->answer($question),
                report: true,
            );
        } finally {
            $this->left = null;
            $lock->release();
        }

        return filled($answer) ? $answer : null;
    }

    /** Kept for a day under this business: to be rated, and handed back as the thread of the next question. */
    public function remember(string $question, string $answer): string
    {
        $id = (string) Str::uuid();
        $until = now()->addHours(self::KEPT_HOURS);
        Cache::put($this->answerKey($id), ['owner' => $this->owner?->id, 'question' => $question, 'answer' => $answer, 'rated' => false, 'until' => $until->getTimestamp()], $until);

        return $id;
    }

    /**
     * The thread rebuilt from the answers this business really got, the last
     * five pairs: the phone names them, it never writes them, so it cannot
     * hand the model a reply the assistant never gave.
     *
     * @param  list<string>  $ids
     * @return list<array{role: string, text: string}>
     */
    public function thread(array $ids): array
    {
        return collect($ids)
            ->map(fn (string $id): mixed => Cache::get($this->answerKey($id)))
            ->filter(fn (mixed $kept): bool => is_array($kept))
            ->take(-self::THREAD_PAIRS)
            ->flatMap(fn (array $kept): array => [['role' => 'owner', 'text' => $kept['question']], ['role' => 'ai', 'text' => $kept['answer']]])
            ->values()
            ->all();
    }

    /** One thumb per answer, as on the web: a second one, or another business's, changes nothing. */
    public function rate(string $id, string $rating): bool
    {
        if ($this->business === null || ! in_array($rating, [AskFeedback::UP, AskFeedback::DOWN], true)) {
            return false;
        }

        return (bool) Cache::lock('ask-rate:'.$id, 10)->get(function () use ($id, $rating): bool {
            $kept = Cache::get($this->answerKey($id));

            if (! is_array($kept) || $kept['rated']) {
                return false;
            }

            Cache::put($this->answerKey($id), [...$kept, 'rated' => true], now()->setTimestamp($kept['until']));
            AskFeedback::record($this->business, $kept['owner'], $kept['question'], $kept['answer'], $rating);

            return true;
        });
    }

    /**
     * The answer for the phone: a link becomes a door to the app screen of
     * the route it names, and stays words when the app has no such screen.
     *
     * @param  list<string>  $screens
     * @return list<array{kind: string, text: string, target?: array{screen: string, query: array<string, mixed>}}>
     */
    public static function appParts(string $answer, array $screens): array
    {
        return array_map(function (array $part) use ($screens): array {
            if ($part['kind'] !== 'link') {
                return $part;
            }

            $name = rescue(fn (): ?string => Route::getRoutes()->match(Request::create($part['url']))->getName(), report: false);

            if (! in_array($name, $screens, true)) {
                return ['kind' => 'text', 'text' => $part['text']];
            }

            parse_str((string) parse_url($part['url'], PHP_URL_QUERY), $query);

            return ['kind' => 'link', 'text' => $part['text'], 'target' => ['screen' => $name, 'query' => $query]];
        }, AnswerHtml::parts($answer));
    }

    private function answerKey(string $id): string
    {
        return 'ask-answer:'.$this->business?->id.':'.$id;
    }
}
