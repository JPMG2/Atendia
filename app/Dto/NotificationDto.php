<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\NotificationType;

final readonly class NotificationDto
{
    public function __construct(
        public string $message,
        public NotificationType $type,
    ) {}
}
