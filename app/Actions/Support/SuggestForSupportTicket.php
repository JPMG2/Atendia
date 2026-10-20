<?php

declare(strict_types=1);

namespace App\Actions\Support;

use App\Ai\Agents\SupportTicketReviewer;
use App\Classes\Main\AssistantContract;
use App\Classes\Search\Accents;
use App\Enums\SupportTicketStatus;
use App\Models\HelpArticle;
use App\Models\SupportTicket;
use App\Services\Help\HelpFinder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * Prepares what the support team reads first: the AI's reading of a new report
 * and a draft built ONLY from the help articles, stored on the report and never
 * sent to the business. One model call per report; the switch, the reports that
 * need no answer and the look-alike check are decided in PHP before it.
 */
class SuggestForSupportTicket
{
    private const int SUMMARY_MAX = 140;

    private const int REASON_MAX = 300;

    /** The answer box takes 900: a longer draft would be refused the moment it is pasted. */
    private const int DRAFT_MAX = 900;

    /**
     * More than the three the widget suggests: an owner's sentence mixes many loose
     * words, so the right article is often fourth, and offering it costs a few tokens.
     */
    private const int ARTICLES = 6;

    /** Reports of the same business younger than this can be the same problem. */
    private const int LOOK_ALIKE_DAYS = 14;

    /** Shared distinctive words, and how much of the shorter report they cover. */
    private const int LOOK_ALIKE_WORDS = 3;

    private const float LOOK_ALIKE_OVERLAP = 0.6;

    public function __construct(private readonly HelpFinder $help) {}

    /**
     * @return array<string, mixed>|null Null when nothing was prepared.
     */
    public function handle(SupportTicket $ticket): ?array
    {
        if (! $this->wanted($ticket)) {
            return null;
        }

        $articles = $this->help->find($ticket->screen, $ticket->body, self::ARTICLES);

        try {
            $response = new SupportTicketReviewer(AssistantContract::for($ticket->business))
                ->prompt($this->prompt($ticket, $articles));
        } catch (Throwable $e) {
            // The report works without it: a provider that is down must not cost a ticket.
            report($e);

            return null;
        }

        $suggestion = $this->normalize($ticket, $articles, [
            'summary' => $response['summary'] ?? '',
            'kind' => $response['kind'] ?? '',
            'priority' => $response['priority'] ?? '',
            'draft' => $response['draft'] ?? '',
            'used' => $response['used'] ?? [],
            'confidence' => $response['confidence'] ?? '',
            'reason' => $response['reason'] ?? '',
        ]);

        $ticket->forceFill(['ai_suggestion' => $suggestion, 'ai_suggested_at' => now()])->save();

        return $suggestion;
    }

    /**
     * Off by the admin's switch, already done, closed, or a kind that runs no
     * clock (an idea): nobody is waiting for an answer, so no call is made.
     */
    private function wanted(SupportTicket $ticket): bool
    {
        return (int) config('atendia.support.ai_suggestions') === 1
            && $ticket->ai_suggested_at === null
            && ! in_array($ticket->status, [SupportTicketStatus::Resolved, SupportTicketStatus::Closed], true)
            && $ticket->kind?->runs_clock !== false;
    }

    /** @param  Collection<int, HelpArticle>  $articles */
    private function prompt(SupportTicket $ticket, Collection $articles): string
    {
        $help = $articles->isEmpty()
            ? 'No hay ningún artículo de ayuda relacionado: el borrador va vacío.'
            : $articles->map(fn (HelpArticle $article): string => "[slug: {$article->slug}] {$article->title}\n{$article->body}")->implode("\n\n");

        return implode("\n\n", [
            'Reporte de un negocio, marcado como "'.($ticket->kind?->name ?? $ticket->getRawOriginal('kind')).'"'
                .($ticket->screen === null ? '.' : ', desde la pantalla "'.$ticket->screen.'".'),
            "<reporte>\n".mb_substr($ticket->body, 0, 2000)."\n</reporte>",
            "ARTÍCULOS DE AYUDA (la única fuente del borrador):\n".$help,
        ]);
    }

    /**
     * What the model said, kept to what can be trusted: known values only, a
     * draft only when it leans on an article that was really offered.
     *
     * @param  Collection<int, HelpArticle>  $articles
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalize(SupportTicket $ticket, Collection $articles, array $raw): array
    {
        $offered = $articles->pluck('slug')->all();
        $used = array_values(array_intersect($offered, array_map('strval', (array) $raw['used'])));
        $draft = trim((string) $raw['draft']);

        // Grounding enforced in code, not asked of the model: no article behind it, no draft.
        if ($used === [] || $draft === '') {
            $draft = '';
            $used = [];
        }

        return [
            'summary' => mb_substr(trim((string) $raw['summary']), 0, self::SUMMARY_MAX),
            'kind' => in_array($raw['kind'], ['problem', 'question', 'idea'], true) ? $raw['kind'] : $ticket->getRawOriginal('kind'),
            'priority' => in_array($raw['priority'], ['high', 'normal', 'low'], true) ? $raw['priority'] : $ticket->priority->value,
            'draft' => mb_substr($draft, 0, self::DRAFT_MAX),
            'used' => $used,
            'confidence' => $draft === '' ? 'low' : (in_array($raw['confidence'], ['high', 'medium', 'low'], true) ? $raw['confidence'] : 'low'),
            'reason' => mb_substr(trim((string) $raw['reason']), 0, self::REASON_MAX),
            'looks_like' => $this->lookAlike($ticket),
        ];
    }

    /**
     * The code of a recent report of the same business that says much the same,
     * so the team answers once. A heuristic on distinctive words, not the model.
     */
    private function lookAlike(SupportTicket $ticket): ?string
    {
        $mine = $this->words($ticket->body);

        foreach (SupportTicket::siblingsOf((int) $ticket->business_id, $ticket->id, 10) as $other) {
            if ($other->created_at === null || $other->created_at->lt(now()->subDays(self::LOOK_ALIKE_DAYS))) {
                continue;
            }

            $theirs = $this->words($other->body);
            $shared = count(array_intersect($mine, $theirs));
            $shorter = min(count($mine), count($theirs));

            if ($shared >= self::LOOK_ALIKE_WORDS && $shorter > 0 && $shared / $shorter >= self::LOOK_ALIKE_OVERLAP) {
                return $other->code;
            }
        }

        return null;
    }

    /**
     * The words that distinguish a report: long enough to mean something, folded
     * so an accent never splits one.
     *
     * @return list<string>
     */
    private function words(string $text): array
    {
        $words = preg_split('/[^a-z0-9]+/', mb_strtolower(Accents::fold($text)), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($words, fn (string $word): bool => strlen($word) >= 5)));
    }
}
