<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * One moment a number started or stopped answering. Written on transitions
 * only, by whoever noticed first: the connection webhook or the reconcile.
 */
#[Fillable(['business_id', 'connected', 'reason', 'occurred_at'])]
class WhatsAppLinkEvent extends Model
{
    use BelongsToBusiness;

    /** Laravel would snake_case the class into `whats_app_link_events`. */
    protected $table = 'whatsapp_link_events';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['connected' => 'boolean', 'occurred_at' => 'immutable_datetime'];
    }

    /**
     * How the line behaved over a window, for the card under "conectado".
     *
     * Downtime is summed from each fall to the recovery that followed it, so
     * a fall still open counts up to now. `since` is what keeps the number
     * honest on a young account: a business linked yesterday cannot claim
     * seven clean days, and the card says which window it is talking about.
     *
     * @return array{days: int, since: CarbonImmutable, outages: int, downMinutes: int, lastFall: ?CarbonImmutable, lastReason: ?string}
     */
    public static function reliability(Business $business, int $days = 7): array
    {
        $since = CarbonImmutable::now()->subDays($days)->startOfMinute();

        // The window the card NAMES is the window it counts: an account born
        // three days ago cannot claim a clean week, and reading events from
        // before it would report an outage outside the dates on screen.
        $windowStart = $business->created_at === null
            ? $since
            : CarbonImmutable::parse($business->created_at)->max($since);

        /** @var Collection<int, self> $events */
        $events = self::query()
            ->where('business_id', $business->id)
            ->where('occurred_at', '>=', $windowStart)
            ->orderBy('occurred_at')
            ->get();

        $downMinutes = 0;
        $outages = 0;
        $fellAt = null;
        $lastFall = null;
        $lastReason = null;

        foreach ($events as $event) {
            if (! $event->connected && $fellAt === null) {
                $fellAt = $event->occurred_at;
                $lastFall = $event->occurred_at;
                $lastReason = $event->reason;
                $outages++;

                continue;
            }

            if ($event->connected && $fellAt !== null) {
                $downMinutes += $fellAt->diffInMinutes($event->occurred_at);
                $fellAt = null;
            }
        }

        // A fall nobody has recovered from is still running: it counts up to now.
        if ($fellAt !== null) {
            $downMinutes += $fellAt->diffInMinutes(CarbonImmutable::now());
        }

        return [
            'days' => $days,
            'since' => $windowStart,
            'outages' => $outages,
            'downMinutes' => (int) $downMinutes,
            'lastFall' => $lastFall,
            'lastReason' => $lastReason,
        ];
    }

    /** The transition, recorded once: callers already guard against repeats. */
    public static function record(Business $business, bool $connected, ?string $reason = null): void
    {
        self::query()->create([
            'business_id' => $business->id,
            'connected' => $connected,
            'reason' => $reason,
            'occurred_at' => now(),
        ]);
    }
}
