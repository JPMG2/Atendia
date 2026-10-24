<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\PanelNotificationType;
use App\Messaging\Channels\Push;
use App\Messaging\Push\NewBellNotice;
use App\Models\Business;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pushes "there is news in the bell" to the team's phones. One attempt: the
 * relay offers no idempotency key, so a retry could buzz twice.
 */
class SendBellPush implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $businessId, public string $type) {}

    public function handle(Tenant $tenant): void
    {
        $business = Business::query()->find($this->businessId);
        $type = PanelNotificationType::tryFrom($this->type);

        if ($business === null || $type === null) {
            return;
        }

        $tenant->speakingAs($business, fn (): bool => (new Push($business, [], NewBellNotice::class, [$type]))->send());
    }
}
