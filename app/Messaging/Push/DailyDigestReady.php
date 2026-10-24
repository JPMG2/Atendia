<?php

declare(strict_types=1);

namespace App\Messaging\Push;

use App\Messaging\PushMessage;
use App\Models\Company;
use App\Models\User;

/**
 * "The day's report is ready." Generic like every push: the numbers and the
 * most asked questions stay in the app, read through the API. Only the owner
 * gets it, the same door the report itself has (`manage-business`).
 */
class DailyDigestReady extends PushMessage
{
    public function title(): string
    {
        return Company::brand();
    }

    public function body(): string
    {
        return __('push.digest.body');
    }

    public function data(): array
    {
        return ['screen' => 'digest'];
    }

    public function wants(User $user): bool
    {
        return $user->can('manage-business');
    }
}
