<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\Client;
use App\Classes\Main\Team;
use App\Dto\DepartmentDto;
use App\Livewire\Forms\Team\DepartmentForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A department of "Equipo", rule for rule as the web's department sheet
 * validates it. A PUT is partial: what the phone leaves out stays as stored,
 * and a week sent day by day only changes those days.
 */
class SaveDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $team = $this->team();

        // Departments are a plan feature: the web never offers the sheet without it.
        abort_unless($team->hasDepartments, 403, __('team.notify.plan_needed'));

        $stored = [];

        if ($this->editing()) {
            $department = $team->departments->firstWhere('id', (int) $this->route('department'));
            abort_if($department === null, 404);

            $stored = DepartmentDto::fromStored($department->hours, $department->name, $department->routing_hint, $department->users->pluck('id')->all())->toArray();
        }

        // Only text goes in as text and only a list as a list: anything else is read as nothing sent for that box.
        $sent = array_filter($this->only(['name', 'routing_hint']), fn (mixed $value): bool => is_string($value) || $value === null);

        if ($this->has('uses_business_hours')) {
            $sent['uses_business_hours'] = $this->boolean('uses_business_hours');
        }

        if (is_array($this->input('user_ids'))) {
            $sent['user_ids'] = array_values(array_filter($this->input('user_ids'), 'is_scalar'));
        }

        $sentWeek = collect((array) $this->input('week', []))
            ->filter(fn (mixed $row): bool => is_array($row))
            // An hour sent empty arrives as null and must stay null: it is "nothing", not "keep the stored one".
            ->map(fn (array $row): array => array_filter($row, fn (mixed $value): bool => $value === null || is_scalar($value)))
            ->all();
        $week = array_replace_recursive($stored['week'] ?? (new DepartmentDto)->week, $sentWeek);

        $payload = DepartmentDto::fromArray([...$stored, ...$sent, 'week' => $week])->toPayload();

        // The DTO fills a missing hour with 09:00; an hour sent empty has to reach the rules as empty.
        $payload['week'] = collect(range(0, 6))->mapWithKeys(fn (int $day): array => [$day => [
            'open' => filter_var($week[$day]['open'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'opens' => (string) ($week[$day]['opens'] ?? ''),
            'closes' => (string) ($week[$day]['closes'] ?? ''),
        ]])->all();

        $this->replace($payload);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return DepartmentForm::fieldRules(
            (bool) $this->input('uses_business_hours'),
            (array) $this->input('week'),
            $this->user()->business_id,
            $this->editing() ? (int) $this->route('department') : null,
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return DepartmentForm::fieldNames();
    }

    private function editing(): bool
    {
        return $this->route('department') !== null;
    }

    private function team(): Team
    {
        $team = Client::for($this->user())->team;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($team === null, 404);

        return $team;
    }
}
