<?php

declare(strict_types=1);

namespace App\Livewire\Forms;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Facades\Validator;
use Livewire\Form;

abstract class BaseForm extends Form
{
    private ?NotificationService $notificationService = null;

    /**
     * Transform the form's public state into the payload sent to the service layer.
     *
     * @return array<string, mixed>
     */
    abstract protected function transformServiceData(): array;

    /**
     * Build the validation rules for the transformed payload.
     *
     * @return array<string, mixed>
     */
    abstract protected function getValidationRules(?int $excludeId = null): array;

    protected function notificationService(): NotificationService
    {
        return $this->notificationService ??= resolve(NotificationService::class);
    }

    /**
     * Validate the transformed payload and return the validated data.
     *
     * @return array<string, mixed>
     */
    protected function validateServiceData(?int $excludeId = null): array
    {
        return Validator::make(
            $this->transformServiceData(),
            $this->getValidationRules($excludeId),
            [],
            $this->getValidationAttributes(),
        )->validate();
    }

    /**
     * Execute a callback and convert any thrown exception into an error notification.
     *
     * @param  callable():NotificationDto  $callback
     */
    protected function tryAction(callable $callback, string $errorMessage): NotificationDto
    {
        try {
            return $callback();
        } catch (Exception $e) {
            report($e);

            return new NotificationDto($errorMessage, NotificationType::Error);
        }
    }
}
