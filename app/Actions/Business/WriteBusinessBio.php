<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Ai\Agents\BusinessBioWriter;
use App\Models\Business;
use Throwable;

/**
 * Proposes the presentation of a business from what it already told us. It
 * returns the text instead of storing it: the owner reviews and saves.
 */
class WriteBusinessBio
{
    /** Enough of the catalog to ground the line without paying for all of it. */
    private const int SAMPLE = 15;

    /**
     * @param  string|null  $name  the name on screen, which may not be saved yet
     * @return string|null the proposal, or null when the model never answered
     */
    public function handle(Business $business, ?string $name = null): ?string
    {
        $name = trim($name ?? '') !== '' ? trim((string) $name) : $business->name;

        if (trim((string) $name) === '') {
            return null;
        }

        try {
            $response = new BusinessBioWriter()->prompt($this->ask($business, $name));
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        $written = trim((string) ($response['description'] ?? ''));

        return $written !== '' ? $written : null;
    }

    private function ask(Business $business, string $name): string
    {
        $trade = $business->activities()->pluck('name')->implode(', ');
        $offers = $business->services()->pluck('name')
            ->merge($business->products()->pluck('name'))
            ->take(self::SAMPLE)
            ->implode(', ');

        return "Negocio: {$name}"
            .($trade !== '' ? "\nRubro: {$trade}" : '')
            .($offers !== '' ? "\nOfrece: {$offers}" : '');
    }
}
