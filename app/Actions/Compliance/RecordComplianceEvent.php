<?php

declare(strict_types=1);

namespace App\Actions\Compliance;

use App\Actions\Admin\MirrorComplianceEvent;
use App\Enums\ComplianceEventKind;
use App\Models\ComplianceEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Writes one line in a business's file.
 *
 * Never throws: the file is a record OF what happened, so a failure to write
 * it must not undo the suspension or the payment it was recording. It reports
 * and goes on, which is also why every caller can call it unguarded.
 */
class RecordComplianceEvent
{
    public function handle(
        int $businessId,
        ComplianceEventKind $kind,
        ?string $detail = null,
        ?int $actorId = null,
        ?CarbonInterface $at = null,
    ): ?ComplianceEvent {
        try {
            $line = ComplianceEvent::query()->create([
                'business_id' => $businessId,
                'kind' => $kind,
                // An appeal is the business's own words: an excerpt, never the whole text.
                'detail' => $detail === null ? null : Str::limit(trim($detail), 200, '…'),
                'actor_id' => $actorId,
                'occurred_at' => $at ?? now(),
            ]);

            // The admin hears every penalty from this one door; its own failure
            // must not take the line just written down with it.
            rescue(fn () => app(MirrorComplianceEvent::class)->handle($line));

            return $line;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
