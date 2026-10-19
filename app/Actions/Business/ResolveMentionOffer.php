<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Dto\MentionOfferDto;
use App\Enums\MentionOfferState;
use App\Enums\MentionStatus;
use App\Models\Business;
use App\Models\BusinessMention;
use App\Models\Company;
use App\Models\MentionGoal;
use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Collection;

/**
 * Decides what the mention card says for a business: nothing, a new offer, or
 * where an accepted one stands. The patience rules (a pause after a "no", a
 * limit of "no"s, only a bigger goal reopens it) live here and nowhere else.
 */
class ResolveMentionOffer
{
    public function handle(Business $business): ?MentionOfferDto
    {
        $mentions = $business->mentions()->with('socialNetwork:id,name')->get();

        $open = $mentions->first(fn (BusinessMention $mention): bool => $mention->status->isOpen());

        if ($open !== null) {
            return new MentionOfferDto($this->stateOf($open->status), $open->goal_threshold, mention: $open);
        }

        if ($this->isResting($mentions)) {
            return null;
        }

        $goal = MentionGoal::reachedBy($business->conversationsThisMonth(), (int) $mentions->max('goal_threshold'));
        $accounts = $goal === null ? null : $this->accountsFor($business);

        if ($goal === null || $accounts === null) {
            return null;
        }

        [$platformLink, $businessLink, $platformLinks] = $accounts;

        return new MentionOfferDto(
            $businessLink === null ? MentionOfferState::NeedsNetwork : MentionOfferState::Offer,
            $goal->threshold,
            goal: $goal,
            platformLink: $platformLink,
            businessLink: $businessLink,
            platformLinks: $platformLinks,
        );
    }

    private function stateOf(MentionStatus $status): MentionOfferState
    {
        return match ($status) {
            MentionStatus::NotFollowing => MentionOfferState::NotFollowing,
            MentionStatus::Ready => MentionOfferState::Ready,
            default => MentionOfferState::Pending,
        };
    }

    /**
     * Too many "no"s, or too recent a one: the offer waits. Even then a bigger
     * goal is a new reason, but not before the pause is over.
     *
     * @param  iterable<int, BusinessMention>  $mentions
     */
    private function isResting(iterable $mentions): bool
    {
        $refusals = collect($mentions)->filter(fn (BusinessMention $mention): bool => $mention->status->isRefusal());

        if ($refusals->count() >= (int) config('atendia.mentions.max_refusals')) {
            return true;
        }

        $last = $refusals->max('answered_at');

        return $last !== null && $last->gt(now()->subMonths((int) config('atendia.mentions.pause_months')));
    }

    /**
     * The platform's account where the business is also present (the network
     * the mention is tagged on, the first by the order she keeps them), the
     * business's own account there, and ALL the platform's registered accounts
     * for the follow. Null when the platform has none registered; with no
     * network in common the business link comes back null.
     *
     * @return array{0: SocialLink, 1: ?SocialLink, 2: Collection<int, SocialLink>}|null
     */
    private function accountsFor(Business $business): ?array
    {
        $platform = Company::current()?->socialLinks()->with('socialNetwork:id,name')->orderBy('sort_order')->get();

        if ($platform === null || $platform->isEmpty()) {
            return null;
        }

        $own = $business->socialLinks()->get()->keyBy('social_network_id');

        foreach ($platform as $link) {
            if ($own->has($link->social_network_id)) {
                return [$link, $own->get($link->social_network_id), $platform];
            }
        }

        return [$platform->first(), null, $platform];
    }
}
