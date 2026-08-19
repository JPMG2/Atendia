<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class NotificationService
{
    /**
     * @return array{entity: string, gender: 'male'|'female'}
     */
    private function contextFor(string $tableName): array
    {
        return [
            'users' => ['entity' => __('notifications.entities.user'), 'gender' => 'male'],
            'companies' => ['entity' => __('notifications.entities.company'), 'gender' => 'female'],
            'currencies' => ['entity' => __('notifications.entities.currency'), 'gender' => 'female'],
            'departments' => ['entity' => __('notifications.entities.department'), 'gender' => 'male'],
            'sequences' => ['entity' => __('notifications.entities.sequence'), 'gender' => 'female'],
            'countries' => ['entity' => __('notifications.entities.country'), 'gender' => 'male'],
            'provinces' => ['entity' => __('notifications.entities.province'), 'gender' => 'female'],
            'regions' => ['entity' => __('notifications.entities.region'), 'gender' => 'female'],
            'tax_conditions' => ['entity' => __('notifications.entities.tax_condition'), 'gender' => 'female'],
            'social_networks' => ['entity' => __('notifications.entities.social_network'), 'gender' => 'female'],
            'current_statuses' => ['entity' => __('notifications.entities.status'), 'gender' => 'male'],
            'business_sectors' => ['entity' => __('notifications.entities.business_sector'), 'gender' => 'male'],
            'business_activities' => ['entity' => __('notifications.entities.business_activity'), 'gender' => 'female'],
            'blood_types' => ['entity' => __('notifications.entities.blood_type'), 'gender' => 'female'],
            'document_types' => ['entity' => __('notifications.entities.document_type'), 'gender' => 'male'],
            'genders' => ['entity' => __('notifications.entities.gender'), 'gender' => 'male'],
            'marital_statuses' => ['entity' => __('notifications.entities.marital_status'), 'gender' => 'male'],
            'occupations' => ['entity' => __('notifications.entities.occupation'), 'gender' => 'female'],
        ][mb_strtolower($tableName)] ?? [
            'entity' => __('notifications.entities.record'),
            'gender' => 'male',
        ];
    }

    public function notificationFor(Model $model, string $action): NotificationDto
    {
        $actionMap = [
            'create' => 'created',
            'created' => 'created',
            'update' => 'updated',
            'updated' => 'updated',
            'delete' => 'deleted',
            'deleted' => 'deleted',
        ];

        $normalizedAction = $actionMap[$action] ?? null;

        if ($normalizedAction === null) {
            throw new InvalidArgumentException(__('notifications.invalid_action', ['action' => $action]));
        }

        return $this->{$normalizedAction}($model);
    }

    public function created(Model $model): NotificationDto
    {
        $context = $this->contextFor($model->getTable());

        if ($model->wasRecentlyCreated) {
            return new NotificationDto(
                __('notifications.created.'.$context['gender'], ['entity' => $context['entity']]),
                NotificationType::Success,
            );
        }

        return new NotificationDto(__('notifications.not_created'), NotificationType::Error);
    }

    public function updated(Model $model): NotificationDto
    {
        $context = $this->contextFor($model->getTable());

        if ($model->wasChanged()) {
            return new NotificationDto(
                __('notifications.updated.'.$context['gender'], ['entity' => $context['entity']]),
                NotificationType::Success,
            );
        }

        return new NotificationDto(__('notifications.no_changes'), NotificationType::Info);
    }

    public function deleted(Model $model): NotificationDto
    {
        $context = $this->contextFor($model->getTable());

        return new NotificationDto(
            __('notifications.deleted.'.$context['gender'], ['entity' => $context['entity']]),
            NotificationType::Success,
        );
    }
}
