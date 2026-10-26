<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Models\HelpArticle;
use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Collection;

/**
 * The help tab of the support desk as data, with its words: the answers that are not answering, the ones
 * nobody has touched in months, the screens reported most and how often a read answer still ended in a report.
 * The web screen and the mobile API read from here.
 */
class SupportHelpDigest
{
    /** @var array{opened: int, tickets: int} */
    public array $deflection {
        get => $this->deflection ??= HelpArticle::deflection();
    }

    /** @var Collection<int, HelpArticle> */
    public Collection $failing {
        get => $this->failing ??= HelpArticle::failing();
    }

    /** @var Collection<int, HelpArticle> */
    public Collection $stale {
        get => $this->stale ??= HelpArticle::stale();
    }

    /** @var array<int, object{screen: string|null, total: int}> */
    public array $pain {
        get => $this->pain ??= SupportTicket::painPoints()->all();
    }

    /** @var array{title: string, body: string}|null the empty state, only when there is nothing at all to look at */
    public ?array $empty {
        get => $this->deflection['opened'] === 0 && $this->stale->isEmpty() && $this->failing->isEmpty() && $this->pain === []
            ? ['title' => __('support.admin.help_empty_title'), 'body' => __('support.admin.help_empty_body')]
            : null;
    }

    /**
     * The only number that says whether any of the help works: of all the answers read, how many still ended in a report.
     *
     * @var array{title: string, text: string}|null
     */
    public ?array $deflectionCard {
        get {
            $deflection = $this->deflection;

            if ($deflection['opened'] === 0) {
                return null;
            }

            // Two choices, not one sentence: "1 terminaron" is the kind of dangling grammar everybody notices.
            return [
                'title' => __('support.admin.deflection_title'),
                'text' => trans_choice('support.admin.deflection_opened', $deflection['opened'], ['count' => $deflection['opened']])
                    .' '.trans_choice('support.admin.deflection_tickets', $deflection['tickets'], ['count' => $deflection['tickets']]),
            ];
        }
    }

    /** @var array{title: string, rows: list<array{id: int, title: string, count: int}>}|null a failing article is tomorrow's ticket */
    public ?array $failingCard {
        get => $this->failing->isEmpty() ? null : [
            'title' => __('support.admin.failing_title'),
            'rows' => $this->failing->map(fn (HelpArticle $article): array => ['id' => $article->id, 'title' => $article->title, 'count' => (int) $article->unhelpful_count])->values()->all(),
        ];
    }

    /** @var array{title: string, rows: list<array{id: int, title: string, age: string}>}|null written long ago and never touched */
    public ?array $staleCard {
        get => $this->stale->isEmpty() ? null : [
            'title' => __('support.admin.stale_title'),
            'rows' => $this->stale->map(fn (HelpArticle $article): array => ['id' => $article->id, 'title' => $article->title, 'age' => (string) $article->updated_at?->diffForHumans()])->values()->all(),
        ];
    }

    /** @var array{title: string, rows: list<array{screen: string, total: int}>}|null the same screen reported over and over is the thing to fix */
    public ?array $painCard {
        get => $this->pain === [] ? null : [
            'title' => __('support.admin.pain_title'),
            'rows' => array_map(fn (object $point): array => ['screen' => SupportQueue::screenName($point->screen), 'total' => $point->total], $this->pain),
        ];
    }
}
