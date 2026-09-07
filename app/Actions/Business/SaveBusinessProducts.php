<?php

declare(strict_types=1);

namespace App\Actions\Business;

class SaveBusinessProducts extends ReconcileBusinessList
{
    protected function relation(): string
    {
        return 'products';
    }
}
