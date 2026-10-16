<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\Models\Appointment;
use App\Models\Business;
use App\Models\ConversationQuestion;

/**
 * Deletes one service from the business's menu, unless the business has
 * history on it.
 *
 * An appointment booked for it, or a customer question about it, are the
 * business's own record: erasing the service would leave them pointing at
 * nothing. Those keep it, and the way to stop offering it is Pause.
 */
class DeleteBusinessService
{
    public const string DELETED = 'deleted';

    public const string HELD = 'held';

    public const string MISSING = 'missing';

    public function handle(Business $business, int $id): string
    {
        $service = $business->services()->find($id);

        if ($service === null) {
            return self::MISSING;
        }

        $held = Appointment::query()->where('service_id', $id)->exists()
            || ConversationQuestion::query()->where('service_id', $id)->exists();

        if ($held) {
            return self::HELD;
        }

        $service->delete();

        return self::DELETED;
    }
}
