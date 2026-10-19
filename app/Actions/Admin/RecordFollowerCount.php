<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\Company;
use App\Models\SocialFollowerCount;
use App\Models\User;

/**
 * Writes today's count for one of the platform's own accounts. Counting twice
 * the same day corrects the number instead of stacking a second one, and an
 * account that is not the platform's is refused: this desk is for ours only.
 */
class RecordFollowerCount
{
    public function handle(int $socialLinkId, int $followers, User $by): ?SocialFollowerCount
    {
        $ours = Company::current()?->socialLinks()->whereKey($socialLinkId)->exists() ?? false;

        if (! $ours) {
            return null;
        }

        return SocialFollowerCount::query()->updateOrCreate(
            ['social_link_id' => $socialLinkId, 'counted_on' => today()],
            ['followers' => $followers, 'counted_by' => $by->id],
        );
    }
}
