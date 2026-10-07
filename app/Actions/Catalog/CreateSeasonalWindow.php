<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SeasonalWindow;

class CreateSeasonalWindow
{
    /**
     * @param  array{
     *     name: string,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     priority: int,
     *     is_active: bool
     * }  $data  Already validated by SeasonalWindowForm::transformServiceData().
     */
    public function handle(array $data): SeasonalWindow
    {
        return SeasonalWindow::query()->create($data);
    }
}
