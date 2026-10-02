<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\SearchesText;
use Database\Factories\HelpArticleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One answer to one problem. Not tenant data: the same article serves every
 * business, which is why it carries no business_id.
 */
#[Fillable(['slug', 'category', 'screen', 'title', 'body', 'keywords', 'sort_order', 'is_active'])]
class HelpArticle extends Model
{
    /** @use HasFactory<HelpArticleFactory> */
    use HasFactory;

    use SearchesText;

    /** Three, like Zendesk and Intercom: a fourth is a list, not a hint. */
    public const int SUGGESTIONS = 3;

    /** Past this, "closest" stops meaning related and starts meaning random. */
    private const float MAX_DISTANCE = 0.55;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'embedding' => 'array'];
    }

    /** What gets embedded: the title leads, the keywords carry her words. */
    public function embeddableText(): string
    {
        return trim($this->title."\n".($this->keywords ?? '')."\n".$this->body);
    }

    /**
     * Articles still without a vector, for the command that fills them.
     *
     * @return EloquentCollection<int, static>
     */
    public static function withoutEmbedding(): EloquentCollection
    {
        return static::query()->active()->whereNull('embedding')->get();
    }

    /**
     * The closest in meaning, for when her words are not ours. Only called
     * when the words lane came back empty — that is what keeps it cheap.
     *
     * @param  list<float>  $vector
     * @return EloquentCollection<int, static>
     */
    public static function closestInMeaning(array $vector, int $limit = self::SUGGESTIONS): EloquentCollection
    {
        return static::query()
            ->active()
            ->whereNotNull('embedding')
            // Explicit: asking for the distance alone replaces the default
            // star, and the rows come back without their own columns.
            ->select('help_articles.*')
            ->selectVectorDistance('embedding', $vector, as: 'distance')
            ->orderByVectorDistance('embedding', $vector)
            ->limit($limit)
            ->get()
            ->filter(fn (self $article): bool => (float) $article->getAttribute('distance') <= self::MAX_DISTANCE)
            ->values();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Everything readable, in reading order.
     *
     * @return EloquentCollection<int, static>
     */
    public static function shelf(): EloquentCollection
    {
        return static::query()->active()->orderBy('category')->orderBy('sort_order')->get();
    }

    /**
     * What to suggest without being asked: the app picks by SCREEN, which is
     * the one thing we know for sure, and only then by what she typed.
     *
     * @return EloquentCollection<int, static>
     */
    public static function suggest(?string $screen, string $term = '', int $limit = self::SUGGESTIONS): EloquentCollection
    {
        $term = trim($term);

        if ($term !== '') {
            return static::query()
                ->active()
                // By WORDS, and only in the title and the keywords: the body
                // mentions "panel" or "pantalla" everywhere, so matching it
                // turns any sentence into a hit and the meaning lane dead.
                ->whereAnyWordMatches(['title', 'keywords'], $term)
                ->orderByLeadingMatch('title', $term)
                // Its own screen breaks the tie: same words, closer problem.
                ->orderByRaw('case when screen = ? then 0 else 1 end', [$screen])
                ->orderBy('sort_order')
                ->limit($limit)
                ->get();
        }

        if ($screen === null) {
            return new EloquentCollection;
        }

        return static::query()
            ->active()
            ->where('screen', $screen)
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    /** How many months before an answer deserves a second look. */
    private const int STALE_MONTHS = 6;

    /**
     * Written a while ago and never touched since. An outdated article is one
     * of the named anti-patterns, and nothing else in the panel would say so.
     *
     * @return EloquentCollection<int, static>
     */
    public static function stale(int $top = 3): EloquentCollection
    {
        return static::query()
            ->active()
            ->where('updated_at', '<', now()->subMonths(self::STALE_MONTHS))
            ->orderBy('updated_at')
            ->limit($top)
            ->get();
    }

    /**
     * Reading counted apart from voting: most people never vote, and the
     * opens against the tickets opened from help is the deflection number.
     */
    public function markOpened(): void
    {
        $this->increment('opened_count');
    }

    /**
     * The one number that says whether the help is working: of everyone who
     * opened an answer, how many still had to report.
     *
     * @return array{opened: int, tickets: int}
     */
    public static function deflection(): array
    {
        return [
            'opened' => (int) static::query()->sum('opened_count'),
            'tickets' => SupportTicket::query()->where('after_help', true)->count(),
        ];
    }

    /**
     * The ones that are failing: more thumbs down than up, worst first. People
     * mostly bother to vote when an article did not help them, so this list is
     * a rewriting queue, not a popularity chart.
     *
     * @return EloquentCollection<int, static>
     */
    public static function failing(int $top = 3): EloquentCollection
    {
        return static::query()
            ->active()
            ->whereColumn('unhelpful_count', '>', 'helpful_count')
            ->where('unhelpful_count', '>', 0)
            ->orderByDesc('unhelpful_count')
            ->limit($top)
            ->get();
    }

    /**
     * The help screen's own search, which DOES read the body: there she is
     * looking on purpose and a wide net helps, unlike a hint offered unasked.
     *
     * @return EloquentCollection<int, static>
     */
    public static function searchAll(string $term, int $limit = 50): EloquentCollection
    {
        return static::query()
            ->active()
            ->whereAnyWordMatches(['title', 'keywords', 'body'], $term)
            ->orderByLeadingMatch('title', $term)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();
    }

    /** One article by id, for the screen acting on a row it just listed. */
    public static function locate(int $id): ?static
    {
        return static::query()->find($id);
    }

    /**
     * The vote, counted apart. The thumb DOWN is the one worth reading: people
     * mostly bother to vote when the article did not help them.
     */
    public function rate(bool $helpful): void
    {
        $this->increment($helpful ? 'helpful_count' : 'unhelpful_count');
    }
}
