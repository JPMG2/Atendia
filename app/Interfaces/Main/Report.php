<?php

declare(strict_types=1);

namespace App\Interfaces\Main;

use App\Dto\ReportDto;
use App\Models\User;

/**
 * WHAT a report contains, once, for every format. Registered by key in
 * config('atendia.reports'); the lock is here, not in hiding the button.
 */
interface Report
{
    public function authorize(User $user): bool;

    public ReportDto $document { get; }
}
