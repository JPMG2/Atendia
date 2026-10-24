<?php

declare(strict_types=1);

namespace App\Messaging\Push;

use App\Enums\PanelNotificationType;
use App\Messaging\PushMessage;
use App\Models\Company;
use App\Models\PanelNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * "There is news in the bell." The line is the same for every kind on purpose:
 * the detail is read through the API once the app is open, like the socket's
 * "look again" event. Worded without a second-person verb so voseo needs no
 * override.
 */
class NewBellNotice extends PushMessage
{
    public function __construct(Model $model, public PanelNotificationType $type)
    {
        parent::__construct($model);
    }

    public function title(): string
    {
        return Company::brand();
    }

    public function body(): string
    {
        return __('push.bell.body');
    }

    public function data(): array
    {
        return ['screen' => 'notifications'];
    }

    /** A kind the person muted in the bell stays quiet here too. */
    public function wants(User $user): bool
    {
        return ! in_array($this->type->value, $user->mutedBellTypes(), true);
    }

    /** What the bell would show as unread for this person, under the business scope the job runs in. */
    public function badgeFor(User $user): ?int
    {
        return PanelNotification::unreadCountFor($user);
    }
}
