<?php

declare(strict_types=1);

namespace App\Actions\Professionals;

use App\Models\Professional;
use RuntimeException;

/**
 * Takes the professional off the lists; their past bookings keep pointing at
 * them. Never with bookings still to come: those clients would be left with
 * nobody, and nothing is cancelled behind anyone's back. Switching them off
 * is the way to stop new bookings meanwhile.
 */
class DeleteProfessional
{
    /**
     * @throws RuntimeException when bookings are still to come
     */
    public function handle(Professional $professional): void
    {
        if ($professional->upcomingCount() > 0) {
            throw new RuntimeException('The professional still has bookings to come.');
        }

        $professional->delete();
    }
}
