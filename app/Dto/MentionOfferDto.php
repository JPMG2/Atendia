<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\MentionOfferState;
use App\Models\BusinessMention;
use App\Models\MentionGoal;
use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Collection;

/** What the mention card shows: the state, the goal behind it and the accounts involved. */
class MentionOfferDto
{
    /**
     * Every account of the platform the business has to follow: only the ones
     * registered, each with its link. An accepted mention shows the list it
     * was accepted with, even if the platform adds an account afterwards.
     *
     * @var list<array{network: string, url: string}>
     */
    public array $accounts {
        get => $this->mention?->platform_accounts
            ?? $this->platformLinks->map(fn (SocialLink $link): array => [
                'network' => (string) $link->socialNetwork?->name,
                'url' => $link->url,
            ])->values()->all();
    }

    /** The networks as a sentence: "Instagram y TikTok". */
    public string $accountNames {
        get => collect($this->accounts)->pluck('network')->join(', ', ' y ');
    }

    /** @param  Collection<int, SocialLink>  $platformLinks */
    public function __construct(
        public readonly MentionOfferState $state,
        public readonly int $threshold,
        public readonly ?MentionGoal $goal = null,
        public readonly ?BusinessMention $mention = null,
        public readonly ?SocialLink $platformLink = null,
        public readonly ?SocialLink $businessLink = null,
        public readonly Collection $platformLinks = new Collection,
    ) {}
}
