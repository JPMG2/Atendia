<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformSensorStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One look at one infrastructure sensor, kept a day so the board can draw
 * how it behaved and not only how it reads now. The platform's own table:
 * it has no tenant, only the admin panel reads it.
 */
#[Fillable(['sensor_key', 'status', 'checked_at'])]
class PlatformSensorReading extends Model
{
    /** How long a look is worth keeping: the strip draws one day. */
    public const int HOURS = 24;

    public $timestamps = false;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'status' => PlatformSensorStatus::class,
            'checked_at' => 'datetime',
        ];
    }

    /** Drops what the strip can no longer draw. */
    public static function prune(): void
    {
        self::query()->where('checked_at', '<', now()->subHours(self::HOURS + 1))->delete();
    }

    /**
     * The last day per sensor, one bucket per hour, oldest first. A bucket
     * shows the WORST look of its hour (a 3-minute outage must not vanish
     * into an average) and is null when nobody looked.
     *
     * @param  list<string>  $keys
     * @return array<string, list<PlatformSensorStatus|null>>
     */
    public static function strips(array $keys): array
    {
        $since = now()->startOfHour()->subHours(self::HOURS - 1);
        $rows = self::query()->whereIn('sensor_key', $keys)->where('checked_at', '>=', $since)->get(['sensor_key', 'status', 'checked_at']);

        $strips = [];

        foreach ($keys as $key) {
            $buckets = array_fill(0, self::HOURS, null);

            foreach ($rows->where('sensor_key', $key) as $row) {
                $slot = (int) $since->diffInHours($row->checked_at->copy()->startOfHour());
                $current = $buckets[$slot] ?? null;

                if ($slot >= 0 && $slot < self::HOURS && ($current === null || $row->status->weight() > $current->weight())) {
                    $buckets[$slot] = $row->status;
                }
            }

            $strips[$key] = $buckets;
        }

        return $strips;
    }
}
