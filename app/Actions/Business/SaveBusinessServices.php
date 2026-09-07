<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\SuggestedService;
use Illuminate\Database\Eloquent\Model;

class SaveBusinessServices extends ReconcileBusinessList
{
    protected function relation(): string
    {
        return 'services';
    }

    /**
     * A name the curated suggestions know adopts their type; an unknown one
     * stays untyped until someone classifies it.
     */
    protected function decorate(Model $row): void
    {
        $row->service_type_id ??= SuggestedService::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower((string) $row->name)])
            ->value('service_type_id');
    }
}
