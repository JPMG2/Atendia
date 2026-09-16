<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Service;

/**
 * Pauses or resumes one service from its row: stop offering it without
 * losing it, the "dejar de ofrecerlo sin borrarlo" the column promises.
 */
class ToggleBusinessService
{
    public function handle(Business $business, int $id): Service
    {
        $service = $business->services()->findOrFail($id);

        $service->is_active = ! $service->is_active;
        $service->save();

        return $service;
    }
}
