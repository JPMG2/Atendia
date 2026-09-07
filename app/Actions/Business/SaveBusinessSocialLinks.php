<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;

/**
 * The social-networks slice, same contract as the company's: one link per
 * network, `sort_order` is the position on screen, and whatever left the
 * screen leaves the table.
 */
class SaveBusinessSocialLinks
{
    /**
     * Says whether the LIST changed, like {@see ReconcileBusinessList}.
     *
     * @param  list<array{social_network_id: int, url: string}>  $links  In display order, already validated by the calling form.
     */
    public function handle(Business $business, array $links): bool
    {
        $changed = false;
        $kept = [];

        foreach ($links as $index => $row) {
            $link = $business->socialLinks()->updateOrCreate(
                ['social_network_id' => $row['social_network_id']],
                ['url' => $row['url'], 'sort_order' => $index],
            );

            $changed = $changed || $link->wasRecentlyCreated || $link->wasChanged();
            $kept[] = $link->id;
        }

        $removed = $business->socialLinks()->whereNotIn('id', $kept)->delete();

        $business->unsetRelation('socialLinks');

        return $changed || $removed > 0;
    }
}
