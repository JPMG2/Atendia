<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\WhatsappVariant;

class CreateWhatsappVariant
{
    /**
     * @param  array{
     *     seasonal_window_id: int|null,
     *     phone: string|null,
     *     opening_schedule: array<int, array{0: string, 1: string}>|null,
     *     is_active: bool
     * }  $data  Already validated by WhatsappVariantForm::transformServiceData().
     */
    public function handle(array $data): WhatsappVariant
    {
        return WhatsappVariant::query()->create($data);
    }
}
