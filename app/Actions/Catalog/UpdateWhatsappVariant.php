<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\WhatsappVariant;

class UpdateWhatsappVariant
{
    /**
     * @param  array{
     *     seasonal_window_id: int|null,
     *     phone: string|null,
     *     opening_schedule: array<int, array{0: string, 1: string}>|null,
     *     is_active: bool
     * }  $data  Already validated by WhatsappVariantForm::transformServiceData().
     */
    public function handle(int $id, array $data): WhatsappVariant
    {
        $variant = WhatsappVariant::query()->findOrFail($id);
        $variant->update($data);

        return $variant;
    }
}
