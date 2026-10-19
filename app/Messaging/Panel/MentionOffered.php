<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;
use App\Models\MentionGoal;
use Illuminate\Database\Eloquent\Model;

/** A goal was reached: the bell tells the owner the offer is waiting on the home. */
class MentionOffered extends PanelMessage
{
    public function __construct(Model $model, public MentionGoal $goal)
    {
        parent::__construct($model);
    }

    public function type(): PanelNotificationType
    {
        return PanelNotificationType::MentionOffered;
    }

    public function dedupeKey(): string
    {
        return 'mention-offer:'.$this->goal->getKey();
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        return ['achievement' => MentionGoal::achievement($this->goal->threshold)];
    }

    public function url(): ?string
    {
        return route('dashboard');
    }

    /** The same goal reported again is the same news: it must stay read once read. */
    public function revives(): bool
    {
        return false;
    }
}
