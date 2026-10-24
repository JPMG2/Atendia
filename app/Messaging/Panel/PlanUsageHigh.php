<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;
use Illuminate\Database\Eloquent\Model;

/**
 * The month's conversations crossed 80% of the plan. Informative only: the
 * assistant never stops answering at the cap (the WhatsApp is the client's
 * cash register), so this row is the owner's heads-up and nothing else.
 */
class PlanUsageHigh extends PanelMessage
{
    public function __construct(Model $model, public int $used = 0, public int $cap = 0)
    {
        parent::__construct($model);
    }

    public function type(): PanelNotificationType
    {
        return PanelNotificationType::PlanUsageHigh;
    }

    /** One row per month, on the business's own calendar. */
    public function dedupeKey(): string
    {
        return 'plan-usage:conversations:'.now($this->model->localTimezone())->format('Y-m');
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        return [
            'used' => number_format($this->used, 0, ',', '.'),
            'cap' => number_format($this->cap, 0, ',', '.'),
            'percent' => (int) round($this->used / max(1, $this->cap) * 100),
        ];
    }

    /** Lands on "Mi plan", where the meter says the rest. */
    public function url(): ?string
    {
        return route('my-plan');
    }

    /** Said once a month: reviving would only mark it unread again for no new reason. */
    public function revives(): bool
    {
        return false;
    }
}
