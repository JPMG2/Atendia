<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\AdoptionNudge;

class CreateAdoptionNudge
{
    /**
     * @param  array{step: string, subject: string, body: string, is_active: bool}  $data  Already validated by AdoptionNudgeForm.
     */
    public function handle(array $data): AdoptionNudge
    {
        return AdoptionNudge::query()->create($data);
    }
}
