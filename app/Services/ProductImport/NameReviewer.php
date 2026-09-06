<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use App\Ai\Agents\ProductNameFixer;
use Throwable;

/**
 * Asks the AI which product names carry a data typo, capped and fail-safe:
 * the AI being down simply means no suggestions, never a blocked upload.
 * Only names that were actually sent may come back — a hallucinated
 * "original" is dropped instead of rewriting a row that does not exist.
 */
class NameReviewer
{
    public const int MAX_NAMES = 100;

    /**
     * @param  list<string>  $names
     * @return array<string, string> Real changes only, original => fixed.
     */
    public function review(array $names): array
    {
        $names = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter(fn (string $name): bool => $name !== '')
            ->unique()
            ->take(self::MAX_NAMES)
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        try {
            $response = new ProductNameFixer()->prompt(
                'Review these product names: '.$names->toJson(JSON_UNESCAPED_UNICODE),
            );

            return collect($response['corrections'] ?? [])
                ->filter(fn (array $fix): bool => $names->contains($fix['original'] ?? null)
                    && trim((string) ($fix['fixed'] ?? '')) !== ''
                    && $fix['fixed'] !== $fix['original'])
                ->mapWithKeys(fn (array $fix): array => [$fix['original'] => mb_substr(trim($fix['fixed']), 0, 255)])
                ->all();
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }
}
