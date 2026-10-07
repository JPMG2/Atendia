<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\SeasonalWindow;

class UpdateSeasonalWindow
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
    public function handle(int $id, array $data): SeasonalWindow
    {
        $window = SeasonalWindow::query()->findOrFail($id);
        $window->update($data);

        return $window;
    }
}
