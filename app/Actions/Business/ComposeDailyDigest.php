<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Ai\Agents\DigestWriter;
use App\Dto\DailyDigestDto;
use App\Models\Business;

/**
 * The owner's read of the day from the tally the inbound worker keeps. Shared
 * by the closing mail and by the "resumen" answer, so the two never disagree.
 */
class ComposeDailyDigest
{
    /** How many customer questions the mail lists: a taste, not the log. */
    private const int QUESTIONS = 5;

    /**
     * @param  non-empty-list<array{from: string, q: string, a: string}>  $entries
     */
    public function handle(Business $business, array $entries): DailyDigestDto
    {
        $log = implode("\n", array_map(
            fn (array $entry): string => "Cliente {$entry['from']}: {$entry['q']} → {$entry['a']}",
            $entries,
        ));

        // A dead model never silences the digest: the counts alone still land.
        $summary = rescue(fn (): string => trim((string) DigestWriter::make()->prompt($log)->text), '', report: true);

        $questions = collect($entries)
            ->pluck('q')
            ->map(fn (string $question): string => trim($question))
            ->filter()
            ->unique()
            ->reverse()
            ->take(self::QUESTIONS)
            ->values()
            ->all();

        return new DailyDigestDto(
            business: $business->name,
            messages: count($entries),
            contacts: count(array_unique(array_column($entries, 'from'))),
            summary: $summary,
            questions: $questions,
            // Silent at zero: an empty cheer reads as pity, not as progress.
            referred: $business->referredThisWeek(),
            appointmentsTomorrow: $business->appointmentsTomorrow(),
        );
    }
}
