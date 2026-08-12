<?php

declare(strict_types=1);

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Traits\HasNotifications;

test('dispatchNotification dispatches the typed notification payload', function (): void {
    $component = new class
    {
        use HasNotifications;

        /** @var array{event: string, type: string, message: string} */
        public array $dispatched = [];

        public function dispatch(string $event, string $type, string $message): void
        {
            $this->dispatched = compact('event', 'type', 'message');
        }
    };

    $component->dispatchNotification(
        new NotificationDto('Guardado correctamente', NotificationType::Success),
    );

    expect($component->dispatched)->toBe([
        'event' => 'notify',
        'type' => 'success',
        'message' => 'Guardado correctamente',
    ]);
});
