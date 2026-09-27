<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Team;

use App\Classes\Main\Client;
use App\Dto\DepartmentDto;
use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Livewire\Forms\BaseForm;
use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * The department sheet: who it is for (the hint the assistant reads to
 * route), who answers, and its own hours or the business's.
 */
class DepartmentForm extends BaseForm
{
    public DepartmentDto $data;

    public ?int $editingId = null;

    public function setup(?int $departmentId = null): void
    {
        $this->editingId = $departmentId;
        $this->resetErrorBag();

        $department = $departmentId === null ? null : $this->client()->team?->departments->firstWhere('id', $departmentId);

        $this->data = $department === null
            ? new DepartmentDto
            : DepartmentDto::fromStored($department->hours, $department->name, $department->routing_hint, $department->users->pluck('id')->all());
    }

    public function save(): NotificationDto
    {
        $team = $this->client()->team;

        if ($team === null || ! $team->hasDepartments) {
            return new NotificationDto(__('team.notify.plan_needed'), NotificationType::Warning);
        }

        $validated = $this->validateServiceData($this->editingId);

        return $this->tryAction(function () use ($team, $validated): NotificationDto {

            $team->saveDepartment($validated, $this->editingId);

            return new NotificationDto(__('team.notify.department_saved', ['name' => $validated['name']]), NotificationType::Success);

        }, __('notifications.not_updated'));
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
        $businessId = Auth::user()?->business_id;
        $ownHours = ! $this->data->uses_business_hours;

        $rules = [
            'name' => [
                ...AttributeValidator::stringValid(true, '2'),
                'max:60',
                Rule::unique('departments', 'name')->where('business_id', $businessId)->ignore($excludeId),
            ],
            'routing_hint' => ['required', 'string', 'min:10', 'max:500', AttributeValidator::xssFree()],
            'uses_business_hours' => ['boolean'],
            'user_ids' => ['array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('business_id', $businessId)],
        ];

        foreach (range(0, 6) as $day) {
            $open = $ownHours && $this->data->week[$day]['open'];
            $rules["week.{$day}.open"] = ['boolean'];
            $rules["week.{$day}.opens"] = $open ? ['required', 'date_format:H:i'] : ['nullable'];
            $rules["week.{$day}.closes"] = $open ? ['required', 'date_format:H:i', "after:week.{$day}.opens"] : ['nullable'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function getValidationAttributes(): array
    {
        $attributes = [
            'name' => __('team.department.name'),
            'routing_hint' => __('team.departments.when'),
            'user_ids' => __('team.department.people'),
        ];

        foreach (range(0, 6) as $day) {
            $attributes["week.{$day}.opens"] = __('team.department.opens');
            $attributes["week.{$day}.closes"] = __('team.department.closes');
        }

        return $attributes;
    }
}
