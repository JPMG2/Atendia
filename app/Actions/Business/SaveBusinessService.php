<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Business;
use App\Models\Service;
use App\Models\SuggestedService;

/**
 * Upserts ONE service through its owner — the editor sheet's writer. The
 * wizard keeps its own name-only reconciler ({@see SaveBusinessServices}).
 */
class SaveBusinessService
{
    /**
     * A new service reusing a trashed name restores that row instead of
     * colliding with it: the unique owner+name outlives a soft delete.
     *
     * @param  array<string, mixed>  $data  Already validated by the calling form.
     */
    public function handle(Business $business, array $data, ?int $id = null): Service
    {
        $service = $id === null
            ? $business->services()->withTrashed()->firstOrNew(['name' => $data['name']])
            : $business->services()->findOrFail($id);

        if ($service->trashed()) {
            $service->restore();
        }

        $service->fill($data);
        $service->service_type_id ??= SuggestedService::typeIdFor($service->name);
        $service->save();

        return $service;
    }
}
