<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Livewire\Forms\Admin\SupportReplyForm;
use App\Models\HelpArticle;
use App\Models\SupportReply;
use App\Models\SupportTicket;
use App\Models\SupportTicketKind;
use App\Models\SupportTicketMessage;

/**
 * One support report as the work panel prints it, with the words of the web: the panel and the mobile API
 * both read from here. Replying, taking it, blocking and resolving are actions and live where they are
 * taken; the file a business attached is not served, only that there is one.
 */
class SupportCase
{
    public function __construct(public readonly SupportTicket $ticket) {}

    /** The report with everything the panel reads loaded in one go; null when it does not exist. */
    public static function find(int $id): ?self
    {
        $ticket = SupportTicket::opened($id);

        return $ticket === null ? null : new self($ticket);
    }

    /** @var array{title: string, subtitle: string} */
    public array $head {
        get => [
            'title' => (string) $this->ticket->business?->name,
            'subtitle' => $this->ticket->code.' · '.$this->ticket->kind?->name.' · '.SupportQueue::screenName($this->ticket->screen),
        ];
    }

    /** @var array{status: array{label: string, tone: string}, overdue: string|null, blocked_late: string|null, waits: string|null} */
    public array $state {
        get {
            $ticket = $this->ticket;
            $waitLabel = $ticket->waitLabel();

            return [
                'status' => ['label' => $ticket->status->adminLabel(), 'tone' => $ticket->status->tone() === 'warning' ? 'warning' : 'brand'],
                'overdue' => $ticket->isOverdue() ? __('support.admin.overdue_long', ['hours' => $ticket->overdueHours()]) : null,
                'blocked_late' => $ticket->isBlockedLate() ? __('support.admin.block.late', ['date' => $ticket->blocked_due->format('d/m')]) : null,
                'waits' => $waitLabel === null ? null : __('support.admin.waits', ['time' => $waitLabel]),
            ];
        }
    }

    /** @var array{assignee: array{label: string, value: string}, priority: array{label: string, value: string, tone: string}} */
    public array $handling {
        get => [
            'assignee' => ['label' => __('support.admin.assignee'), 'value' => $this->ticket->assignee?->name ?? __('support.admin.unassigned')],
            'priority' => [
                'label' => __('support.admin.priority'),
                'value' => $this->ticket->priority->label(),
                'tone' => SupportQueue::toneName($this->ticket->priority->tone()),
            ],
        ];
    }

    /** @var list<SupportTicketMessage> what the team told the business, oldest first */
    public array $answers {
        get => $this->ticket->messages->where('is_internal', false)->values()->all();
    }

    /** @var list<SupportTicketMessage> what the team wrote for itself, oldest first */
    public array $notes {
        get => $this->ticket->messages->where('is_internal', true)->values()->all();
    }

    /** @var list<array{key: string, label: string, badge: int|null}> */
    public array $panels {
        get {
            $notes = count($this->notes);

            return [
                ['key' => 'thread', 'label' => __('support.admin.panel.thread'), 'badge' => null],
                ['key' => 'notes', 'label' => __('support.admin.panel.notes'), 'badge' => $notes > 0 ? $notes : null],
                ['key' => 'customer', 'label' => __('support.admin.panel.customer'), 'badge' => null],
            ];
        }
    }

    /**
     * The report is the first message; what the team said follows it. The attachment is only announced: the
     * app has no download for it yet.
     *
     * @var array{report: array{body: string, author: string, at: string, attachment: string|null}, answers: list<array{id: int, body: string, author: string, at: string, delivery: array{label: string, tone: string}|null}>, team: string}
     */
    public array $thread {
        get => [
            'report' => [
                'body' => (string) $this->ticket->body,
                'author' => (string) ($this->ticket->user?->name ?? $this->ticket->business?->name),
                'at' => (string) $this->ticket->created_at?->format('d/m/Y H:i'),
                'attachment' => $this->ticket->attachment_path === null ? null : __('support.admin.attachment'),
            ],
            'answers' => array_map(fn (SupportTicketMessage $message): array => [
                'id' => $message->id,
                'body' => (string) $message->body,
                'author' => (string) ($message->author?->name ?? __('support.admin.the_team')),
                'at' => (string) $message->created_at?->format('d/m H:i'),
                'delivery' => $message->delivery === null ? null : ['label' => $message->delivery->label(), 'tone' => $message->delivery->reached() ? 'brand' : 'danger'],
            ], $this->answers),
            'team' => __('support.admin.the_team'),
        ];
    }

    /**
     * What the AI prepared for the open report, ready to paint: names instead of codes, and the kind and
     * priority only when they differ from what it has now. Prepared for the team only.
     *
     * @var array{title: string, summary: string, confidence: array{label: string, tone: string}, suggests: string|null, looks_like: string|null, draft_label: string, draft: string, sources: string|null, no_draft: string, reason: string, only_team: string}|null
     */
    public ?array $ai {
        get {
            $ticket = $this->ticket;
            $ai = $ticket->ai_suggestion;

            if (! is_array($ai) || in_array($ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)) {
                return null;
            }

            // The reading is written by a model: whatever shape it comes back in, it is read as text, never a crash.
            $text = fn (string $key): string => is_scalar($ai[$key] ?? null) ? (string) $ai[$key] : '';
            $kind = SupportTicketKind::byCode($text('kind') ?: null);
            $differs = ($ai['kind'] ?? null) !== $ticket->getRawOriginal('kind') || ($ai['priority'] ?? null) !== $ticket->priority->value;
            $confidence = in_array($text('confidence'), ['high', 'medium', 'low'], true) ? $text('confidence') : 'low';
            $sources = HelpArticle::titlesFor(array_values(array_filter((array) ($ai['used'] ?? []), 'is_scalar')));
            $draft = $text('draft');

            return [
                'title' => __('support.admin.ai.title'),
                'summary' => $text('summary'),
                'confidence' => ['label' => __('support.admin.ai.confidence.'.$confidence), 'tone' => ['high' => 'success', 'medium' => 'warning'][$confidence] ?? 'neutral'],
                'suggests' => $differs && $kind !== null
                    ? __('support.admin.ai.suggests', ['kind' => $kind->name, 'priority' => SupportTicketPriority::tryFrom($text('priority'))?->label() ?? '—'])
                    : null,
                'looks_like' => $text('looks_like') !== '' ? __('support.admin.ai.looks_like', ['code' => $text('looks_like')]) : null,
                'draft_label' => __('support.admin.ai.draft'),
                'draft' => $draft,
                'sources' => $draft !== '' && $sources !== [] ? __('support.admin.ai.sources').': '.implode(' · ', $sources) : null,
                'no_draft' => __('support.admin.ai.no_draft'),
                'reason' => $text('reason'),
                'only_team' => __('support.admin.ai.only_team'),
            ];
        }
    }

    /** @var array{title: string, rows: list<array{label: string, value: string}>}|null what a blocked report still lacks */
    public ?array $handoff {
        get => $this->ticket->status !== SupportTicketStatus::Blocked ? null : [
            'title' => __('support.admin.block.title'),
            'rows' => [
                ['label' => __('support.admin.block.tried'), 'value' => (string) $this->ticket->blocked_tried],
                ['label' => __('support.admin.block.missing'), 'value' => (string) $this->ticket->blocked_missing],
                ['label' => __('support.admin.block.owner'), 'value' => (string) $this->ticket->blocked_owner],
                ['label' => __('support.admin.block.due'), 'value' => (string) $this->ticket->blocked_due?->format('d/m/Y')],
            ],
        ];
    }

    /**
     * What the widget captured, read like a person reads it.
     *
     * @var array{title: string, rows: list<array{label: string, value: string, mono: bool}>, errors: array{label: string, lines: list<string>}|null}|null
     */
    public ?array $context {
        get {
            $evidence = $this->ticket->evidence();

            if ($evidence->isEmpty) {
                return null;
            }

            return [
                'title' => __('support.admin.context'),
                'rows' => array_map(fn (array $row): array => ['label' => $row['label'], 'value' => (string) $row['value'], 'mono' => (bool) $row['mono']], $evidence->rows),
                'errors' => $evidence->errors === [] ? null : [
                    'label' => trans_choice('support.admin.evidence.errors', count($evidence->errors), ['count' => count($evidence->errors)]),
                    'lines' => $evidence->errors,
                ],
            ];
        }
    }

    /** @var array{hint: string, rows: list<array{id: int, body: string, author: string, at: string}>, empty: array{title: string, body: string}} */
    public array $notesSheet {
        get => [
            'hint' => __('support.admin.notes_hint'),
            'rows' => array_map(fn (SupportTicketMessage $note): array => [
                'id' => $note->id,
                'body' => (string) $note->body,
                'author' => (string) ($note->author?->name ?? __('support.admin.the_team')),
                'at' => (string) $note->created_at?->format('d/m H:i'),
            ], $this->notes),
            'empty' => ['title' => __('support.admin.notes_empty_title'), 'body' => __('support.admin.notes_empty')],
        ];
    }

    /**
     * Before writing: whether this customer pays, whether its WhatsApp works, and where an answer will actually arrive.
     *
     * Null when the business behind the report was closed: there is no customer left to describe.
     *
     * @var array{rows: list<array{label: string, value: string, tone: string|null}>, others: array{label: string, first: string, rows: list<array{id: int, text: string}>}, sheet: string, business: int|null}|null
     */
    public ?array $customerSheet {
        get {
            if ($this->ticket->business === null) {
                return null;
            }

            $customer = $this->ticket->customer();
            $payment = SupportQueue::tone($customer->payment);
            $whatsapp = SupportQueue::tone($customer->whatsapp);

            return [
                'rows' => [
                    ['label' => __('support.admin.business'), 'value' => $customer->name, 'tone' => null],
                    ['label' => __('support.admin.customer.plan'), 'value' => $customer->plan ?? '—', 'tone' => null],
                    ['label' => __('support.admin.customer.payment_label'), 'value' => $payment['label'], 'tone' => $payment['tone']],
                    ['label' => __('support.admin.customer.whatsapp_label'), 'value' => $whatsapp['label'], 'tone' => $whatsapp['tone']],
                    $this->reach($customer->hasAlertNumber, $customer->fallbackEmail),
                ],
                'others' => [
                    'label' => __('support.admin.customer.others'),
                    'first' => __('support.admin.customer.first'),
                    'rows' => $customer->others->map(fn (SupportTicket $other): array => [
                        'id' => $other->id,
                        'text' => $other->code.' · '.$other->status->adminLabel().' · '.$other->created_at?->diffForHumans(),
                    ])->values()->all(),
                ],
                'sheet' => __('support.admin.customer.sheet'),
                'business' => $this->ticket->business_id === null ? null : (int) $this->ticket->business_id,
            ];
        }
    }

    /** @var string|null the line a settled report ends on, in place of the composer */
    public ?string $settled {
        get => in_array($this->ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)
            ? __('support.admin.resolved_in', ['time' => $this->ticket->resolvedInLabel() ?? '—'])
            : null;
    }

    /**
     * The web's answer box, for a report still open: its words and what each
     * help article and saved reply pastes into the answer (the article as the
     * web writes its line). Null once the report is settled.
     *
     * @var array<string, mixed>|null
     */
    public ?array $composer {
        get => $this->settled !== null ? null : [
            'label' => __('support.admin.reply'),
            'hint' => __('support.admin.reply_hint'),
            'max' => SupportReplyForm::MAX,
            'send' => __('support.admin.send_reply'),
            'resolve' => __('support.admin.resolve'),
            'use_draft' => __('support.admin.ai.use'),
            'article' => ['label' => __('support.admin.article'), 'placeholder' => __('support.admin.article_placeholder'), 'options' => HelpArticle::shelf()
                ->map(fn (HelpArticle $article): array => ['value' => $article->slug, 'label' => $article->title, 'text' => __('support.admin.article_line', ['title' => $article->title, 'url' => route('help', ['buscar' => $article->title])])])
                ->values()->all()],
            'saved' => ['label' => __('support.admin.saved_reply'), 'placeholder' => __('support.admin.saved_reply_placeholder'), 'options' => SupportReply::shelfWithBodies()],
        ];
    }

    /** @return array{label: string, value: string, tone: string|null} */
    private function reach(bool $hasAlertNumber, ?string $fallbackEmail): array
    {
        $label = __('support.admin.customer.reach');

        return match (true) {
            $hasAlertNumber => ['label' => $label, 'value' => __('support.admin.customer.reach_whatsapp'), 'tone' => null],
            $fallbackEmail !== null => ['label' => $label, 'value' => __('support.admin.customer.reach_email', ['email' => $fallbackEmail]), 'tone' => null],
            default => ['label' => $label, 'value' => __('support.admin.customer.reach_none'), 'tone' => 'danger'],
        };
    }
}
