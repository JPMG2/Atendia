<?php

declare(strict_types=1);

namespace App\Classes\Main;

use App\Actions\Business\SaveBusinessSocialLinks;
use App\Models\Business;
use App\Models\SocialLink;
use Illuminate\Database\Eloquent\Collection;

/**
 * The social-networks piece, backed by the polymorphic social_links table
 * the company already shares.
 */
class SocialMedia
{
    public function __construct(private Business $business) {}

    /**
     * Ordered as the public site shows them (`sort_order`).
     *
     * @return Collection<int, SocialLink>
     */
    public function links(): Collection
    {
        return $this->business->socialLinks;
    }

    public function isComplete(): bool
    {
        return $this->business->socialLinks->isNotEmpty();
    }

    /**
     * Says whether the list changed.
     *
     * @param  list<array{social_network_id: int, url: string}>  $links  In display order.
     */
    public function save(array $links): bool
    {
        return app(SaveBusinessSocialLinks::class)->handle($this->business, $links);
    }
}
