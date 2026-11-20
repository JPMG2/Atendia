<?php

declare(strict_types=1);

namespace App\Messaging\Panel;

use App\Enums\PanelNotificationType;
use App\Messaging\PanelMessage;
use Illuminate\Database\Eloquent\Model;

/** A version that brings something new is out: the bell leads to «Novedades». */
class ReleaseAvailable extends PanelMessage
{
    public function __construct(Model $model, public string $version = '')
    {
        parent::__construct($model);
    }

    public function type(): PanelNotificationType
    {
        return PanelNotificationType::ReleaseAvailable;
    }

    public function dedupeKey(): string
    {
        return 'release:'.$this->version;
    }

    /**
     * @return array<string, string|int>
     */
    public function payload(): array
    {
        return ['version' => $this->version];
    }

    public function url(): ?string
    {
        return route('releases');
    }

    /** Announced once: a re-run of the announcement must not mark it unread again. */
    public function revives(): bool
    {
        return false;
    }
}
