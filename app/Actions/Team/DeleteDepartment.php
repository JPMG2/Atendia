<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Department;

/** Its threads fall back to the whole team (the foreign key nulls them), never to nobody. */
class DeleteDepartment
{
    public function handle(Department $department): void
    {
        $department->delete();
    }
}
