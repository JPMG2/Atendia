<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Client;

use App\Classes\Main\Client;
use App\Classes\Main\OpeningHours;
use App\Dto\NotificationDto;
use App\Dto\ProfessionalDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Models\Service;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The professional sheet: who they are, their own week or the business's,
 * their capacity and daily ceiling, and the services they attend with what
 * changes for them. Empty means inherit — the sheet never asks twice.
 */
class ProfessionalForm extends BaseForm
{
    public ProfessionalDto $data;

    public ?int $editingId = null;

    /** Called from the component's `mount()` and whenever the sheet opens. */
    public function setup(?int $professionalId = null): void
    {
        $this->editingId = $professionalId;
        $this->resetErrorBag();

        $this->data = $this->client()->professionals?->sheetOf($professionalId) ?? new ProfessionalDto;
    }

    public function save(): NotificationDto
    {
        $professionals = $this->client()->professionals;

        if ($professionals === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        $validated = $this->validateServiceData($this->editingId);

        return $this->tryAction(function () use ($professionals, $validated): NotificationDto {

            $professionals->save($validated, $this->editingId);

            return new NotificationDto(__('professionals.notify.saved', ['name' => $validated['name']]), NotificationType::Success);

        }, __('notifications.not_updated'));
    }

    /** Never with bookings still to come: the owner is told, and nothing is cancelled. */
    public function remove(): NotificationDto
    {
        $professionals = $this->client()->professionals;

        if ($professionals === null || $this->editingId === null) {
            return new NotificationDto(__('notifications.not_found'), NotificationType::Error);
        }

        try {
            $professionals->delete($this->editingId);
        } catch (RuntimeException) {
            return new NotificationDto(__('professionals.notify.has_bookings'), NotificationType::Warning);
        }

        return new NotificationDto(__('professionals.notify.deleted'), NotificationType::Success);
    }

    /** Rebuilt per request: a Livewire form cannot hold it in a constructor. */
    private function client(): Client
    {
        return Client::for(Auth::user());
    }

    protected function transformServiceData(): array
    {
        return $this->data->toPayload();
    }

    protected function getValidationRules(?int $excludeId = null): array
    {
        return self::fieldRules($this->data->uses_business_hours, $this->data->week, Auth::user()?->business_id, $excludeId);
    }

    /**
     * The sheet's rules, shared with the app's API so both validate a professional alike.
     *
     * @param  array<int, array{open: bool, opens_at: string, closes_at: string}>  $week
     * @return array<string, array<int, mixed>>
     */
    public static function fieldRules(bool $usesBusinessHours, array $week, ?int $businessId, ?int $excludeId = null): array
    {
        $rules = [
            'name' => [
                ...AttributeValidator::stringValid(true, '2'),
                'max:80',
                Rule::unique('professionals', 'name')->where('business_id', $businessId)->whereNull('deleted_at')->ignore($excludeId),
            ],
            'is_active' => ['boolean'],
            'uses_business_hours' => ['boolean'],
            'appointment_capacity' => ['nullable', 'integer', 'min:1', 'max:50'],
            'appointments_per_day' => ['nullable', 'integer', 'min:1', 'max:500'],
            'services' => ['array'],
            'services.*' => ['array'],
            'services.*.duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'services.*.price_type' => ['nullable', Rule::in(Service::PRICE_TYPES)],
            'services.*.price' => [...AttributeValidator::numericDecimal(false), 'max:9999999999'],
            'services.*.deposit' => [...AttributeValidator::numericDecimal(false), 'max:9999999999'],
        ];

        foreach (OpeningHours::DAY_ORDER as $day) {
            $open = ! $usesBusinessHours && ($week[$day]['open'] ?? false);
            $rules["week.{$day}.open"] = ['boolean'];
            $rules["week.{$day}.opens_at"] = $open ? ['required', 'date_format:H:i'] : ['nullable'];
            // No "after": a shift can close past midnight, like the business's own.
            $rules["week.{$day}.closes_at"] = $open ? ['required', 'date_format:H:i', "different:week.{$day}.opens_at"] : ['nullable'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        return self::fieldNames();
    }

    /** @return array<string, string> */
    public static function fieldNames(): array
    {
        $attributes = [
            'name' => __('professionals.fields.name'),
            'appointment_capacity' => __('professionals.fields.capacity'),
            'appointments_per_day' => __('professionals.fields.per_day'),
            'services.*.duration_minutes' => __('professionals.fields.duration'),
            'services.*.price_type' => __('professionals.fields.price_type'),
            'services.*.price' => __('professionals.fields.price'),
            'services.*.deposit' => __('professionals.fields.deposit'),
        ];

        foreach (OpeningHours::DAY_ORDER as $day) {
            $attributes["week.{$day}.opens_at"] = config('nicename.opens_at');
            $attributes["week.{$day}.closes_at"] = config('nicename.closes_at');
        }

        return $attributes;
    }
}
