<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\MentionStatus;
use App\Messaging\Channels\Panel;
use App\Messaging\Panel\MentionPublished;
use App\Models\BusinessMention;
use App\Services\Tenant;

/**
 * The post is out. Its link is the proof that the promise was kept and what
 * the owner's bell opens, so a mention cannot be marked published without one.
 */
class PublishMention
{
    public function handle(int $id, string $postUrl): ?BusinessMention
    {
        $mention = BusinessMention::query()->where('status', MentionStatus::Ready)->find($id);

        if ($mention === null) {
            return null;
        }

        $mention->update(['status' => MentionStatus::Published, 'published_at' => now(), 'post_url' => $postUrl]);

        // The notice belongs to the business: without adopting it the bell lands nowhere.
        app(Tenant::class)->for(
            $mention->business_id,
            fn (): bool => new Panel($mention, [], MentionPublished::class)->send(),
        );

        return $mention;
    }
}
