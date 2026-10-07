<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\DemoTag;

class CreateDemoTag
{
    /**
     * @param  array{
     *     slug: string,
     *     seasonal_window_id: int|null,
     *     label: string|null,
     *     business_name: string|null,
     *     noun: string|null,
     *     chips: list<string>|null,
     *     pool: list<array{side: string, text: string}>|null,
     *     sort_order: int,
     *     is_active: bool
     * }  $data  Already validated by DemoTagForm::transformServiceData().
     */
    public function handle(array $data): DemoTag
    {
        return DemoTag::query()->create($data);
    }
}
