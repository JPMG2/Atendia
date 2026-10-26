<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\SupportTicketStatus;
use App\Models\HelpArticle;
use App\Models\Menu;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * The admin's support queue as data, with the words it prints: the web screen and the mobile API both read
 * from here, so a row, a count or an empty state cannot say one thing on the panel and another on the phone.
 * Answering, assigning and closing are actions and live where they are taken, not here.
 */
class SupportQueue
{
    public const int PAGE = 25;

    /** The most rows a queue ever shows: the web's own ceiling, so the app never promises more than the panel holds. */
    public const int CEILING = 100;

    /** @var list<string> the queues that hold reports; "help" is the analysis tab beside them */
    public const array QUEUES = ['answer', 'waiting', 'blocked', 'resolved'];

    /**
     * @param  string  $queue  one of QUEUES
     * @param  string  $who  a user id, or "none" for the reports nobody has
     */
    public function __construct(
        public readonly string $queue = 'answer',
        public readonly string $kind = '',
        public readonly string $business = '',
        public readonly string $who = '',
        public readonly string $search = '',
        public readonly int $limit = self::CEILING,
    ) {}

    /** The screen's name for a route, or the word for a report that came from no screen. */
    public static function screenName(?string $route): string
    {
        // A page of rows names the same few screens: ask the menu once per route, not once per row.
        static $titles = [];

        return $route === null ? __('support.no_screen') : ($titles[$route] ??= Menu::titleFor($route) ?? $route);
    }

    /** "is-danger" is the web's class for a tone; the API says the tone, not the class. */
    public static function toneName(string $class): string
    {
        return str_starts_with($class, 'is-') ? substr($class, 3) : $class;
    }

    /**
     * @param  array{label: string, tone: string}  $badge
     * @return array{label: string, tone: string}
     */
    public static function tone(array $badge): array
    {
        return ['label' => $badge['label'], 'tone' => self::toneName($badge['tone'])];
    }

    /** @var array{answer: int, waiting: int, blocked: int, resolved: int, late: int, blockedLate: int} */
    public array $counts {
        get => $this->counts ??= SupportTicket::queueCounts();
    }

    public bool $isFiltered {
        get => $this->kind !== '' || $this->business !== '' || $this->who !== '' || trim($this->search) !== '';
    }

    /** @var Collection<int, SupportTicket> one more than the limit, only to know whether something is held back */
    public Collection $loaded {
        get => $this->loaded ??= SupportTicket::inbox(
            $this->queue,
            $this->kind === '' ? null : $this->kind,
            $this->business === '' ? null : (int) $this->business,
            $this->search,
            $this->who === '' ? null : $this->who,
            $this->limit + 1,
        );
    }

    public bool $hasMore {
        get => $this->loaded->count() > $this->limit;
    }

    /** @var array<string, string> every kind, switched off ones too: an old report still has to be found by it */
    public array $kindOptions {
        get => collect(SupportTicketKind::options())->pluck('label', 'value')->all();
    }

    /** What the help tab wants the eyes on, for its badge. */
    public int $helpAttention {
        get => HelpArticle::failing()->count() + HelpArticle::stale()->count();
    }

    /** @var array{title: string, sub: string, total: string, oldest: string|null, as_of: string, medians: string} */
    public array $head {
        get {
            $open = $this->counts['answer'] + $this->counts['waiting'] + $this->counts['blocked'];
            $oldest = SupportTicket::oldestUnanswered();
            $medians = SupportTicket::medianResolution();
            $kinds = $this->kindOptions;

            return [
                'title' => __('support.admin.title'),
                'sub' => __('support.admin.sub'),
                'total' => trans_choice('support.admin.count', $open, ['count' => $open]),
                'oldest' => $oldest === null ? null : __('support.admin.oldest', ['time' => $oldest->waitLabel()]),
                'as_of' => __('admin.home.as_of', ['date' => now()->format('d/m/Y H:i')]),
                // A median with nothing behind it is not a zero: it says why it is missing.
                'medians' => $medians === []
                    ? __('support.admin.median_none')
                    : __('support.admin.median', ['days' => 90]).' '.collect($medians)
                        ->map(fn (string $label, string $kindValue): string => ($kinds[$kindValue] ?? $kindValue).' '.$label)
                        ->implode(' · '),
            ];
        }
    }

    /** @var list<array{key: string, label: string, count: int|null, late: bool}> */
    public array $tabs {
        get {
            $tabs = array_map(fn (string $name): array => [
                'key' => $name,
                'label' => __('support.admin.tabs.'.$name),
                'count' => $this->counts[$name],
                'late' => ($name === 'answer' && $this->counts['late'] > 0) || ($name === 'blocked' && $this->counts['blockedLate'] > 0),
            ], self::QUEUES);

            $attention = $this->helpAttention;

            return [...$tabs, ['key' => 'help', 'label' => __('support.admin.tabs.help'), 'count' => $attention > 0 ? $attention : null, 'late' => false]];
        }
    }

    /** @var array{mine: string, clear: string, search: array{label: string, placeholder: string}, kind: array{label: string, all: string, options: list<array{value: string, label: string}>}, business: array{label: string, all: string, options: list<array{value: string, label: string}>}, who: array{label: string, all: string, options: list<array{value: string, label: string}>}} */
    public array $filters {
        get {
            $options = fn (array $map): array => array_map(fn ($value, $key): array => ['value' => (string) $key, 'label' => (string) $value], $map, array_keys($map));

            return [
                'mine' => __('support.admin.mine'),
                'clear' => __('support.admin.clear_filters'),
                'search' => ['label' => __('support.admin.search'), 'placeholder' => __('support.admin.search_placeholder')],
                'kind' => ['label' => __('support.fields.kind'), 'all' => __('support.admin.all_kinds'), 'options' => $options($this->kindOptions)],
                'business' => ['label' => __('support.admin.business'), 'all' => __('support.admin.all_businesses'), 'options' => $options(SupportTicket::reporters())],
                'who' => ['label' => __('support.admin.assignee'), 'all' => __('support.admin.all_assignees'), 'options' => $options(['none' => __('support.admin.unassigned')] + array_map('strval', User::supportTeam()))],
            ];
        }
    }

    /** @var array{wait: string, report: string, business: string, missing: string, follows: string, assignee: string, priority: string, unassigned: string} */
    public array $labels {
        get => [
            'wait' => __('support.admin.columns.wait'),
            'report' => __('support.admin.columns.report'),
            'business' => __('support.admin.business'),
            'missing' => __('support.admin.columns.missing'),
            'follows' => __('support.admin.columns.follows'),
            'assignee' => __('support.admin.assignee'),
            'priority' => __('support.admin.priority'),
            'unassigned' => __('support.admin.unassigned'),
        ];
    }

    /**
     * Three different truths, said differently: the filters hide everything, nothing came in yet, or this queue is clear.
     *
     * @var array{title: string, body: string}|null
     */
    public ?array $empty {
        get {
            if ($this->loaded->isNotEmpty()) {
                return null;
            }

            if ($this->isFiltered) {
                return ['title' => __('support.admin.no_match_title'), 'body' => __('support.admin.no_match_body')];
            }

            if (array_sum([$this->counts['answer'], $this->counts['waiting'], $this->counts['blocked'], $this->counts['resolved']]) === 0) {
                return ['title' => __('support.admin.empty_title'), 'body' => __('support.admin.empty_body')];
            }

            return ['title' => __('support.admin.empty_queue.'.$this->queue.'.title'), 'body' => __('support.admin.empty_queue.'.$this->queue.'.body')];
        }
    }

    /**
     * @var list<array{id: int, code: string, excerpt: string, kind: string|null, screen: string, late: bool, wait: array{label: string|null, overdue: string|null, note: string|null}, business: array{id: int, name: string, plan: string, payment: array{label: string, tone: string}}|null, assignee: string|null, priority: array{label: string, tone: string}, blocked: array{missing: string, owner: string, late: bool, follows: string}|null}>
     */
    public array $rows {
        get => $this->loaded->take($this->limit)->map(function (SupportTicket $ticket): array {
            $client = $ticket->business === null ? null : $ticket->customer();
            $waitLabel = $ticket->waitLabel();

            return [
                'id' => $ticket->id,
                'code' => $ticket->code,
                'excerpt' => $ticket->excerpt(),
                'kind' => $ticket->kind?->name,
                'screen' => self::screenName($ticket->screen),
                // Over her limit the row is painted, not only tagged: a row is seen from the end of the list.
                'late' => $ticket->isLate(),
                'wait' => [
                    'label' => $waitLabel,
                    'overdue' => $waitLabel !== null && $ticket->isOverdue() ? __('support.admin.overdue', ['hours' => $ticket->overdueHours()]) : null,
                    'note' => match (true) {
                        $waitLabel !== null => ! $ticket->isOverdue() && $ticket->status === SupportTicketStatus::Waiting ? __('support.admin.waiting_business') : null,
                        $ticket->resolvedInLabel() !== null => __('support.admin.resolved_in', ['time' => $ticket->resolvedInLabel()]),
                        default => null,
                    },
                ],
                'business' => $ticket->business_id === null || $client === null ? null : [
                    'id' => (int) $ticket->business_id,
                    'name' => $ticket->business->name,
                    'plan' => $client->plan ?? '—',
                    'payment' => self::tone($client->payment),
                ],
                'assignee' => $ticket->assignee?->name,
                'priority' => ['label' => $ticket->priority->label(), 'tone' => self::toneName($ticket->priority->tone())],
                'blocked' => $ticket->status === SupportTicketStatus::Blocked ? [
                    'missing' => (string) $ticket->blocked_missing,
                    'owner' => (string) $ticket->blocked_owner,
                    'late' => $ticket->isBlockedLate(),
                    'follows' => $ticket->isBlockedLate()
                        ? __('support.admin.block.late', ['date' => $ticket->blocked_due->format('d/m')])
                        : __('support.admin.block.until', ['date' => $ticket->blocked_due?->format('d/m')]),
                ] : null,
            ];
        })->values()->all();
    }
}
