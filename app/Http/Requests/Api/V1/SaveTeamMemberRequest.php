<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\Client;
use App\Classes\Main\Team;
use App\Dto\TeamMemberDto;
use App\Livewire\Forms\Team\TeamMemberForm;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A seat of "Equipo", rule for rule as the web's person sheet validates it.
 * Inviting takes the whole sheet; editing only changes where the handoffs
 * reach the person (a PUT is partial), so the name and the email are not read.
 */
class SaveTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $team = $this->team();

        if ($this->editing()) {
            $member = $team->members->firstWhere('id', (int) $this->route('member'));
            abort_if($member === null, 404);

            $payload = TeamMemberDto::fromArray([
                'whatsapp' => $member->whatsapp,
                'department_ids' => $member->departments->pluck('id')->all(),
                ...$this->boxes($this->only(['whatsapp', 'department_ids'])),
            ])->toPayload();
        } else {
            $payload = TeamMemberDto::fromArray($this->boxes($this->all()))->toPayload();
        }

        // Without the plan's departments there is nowhere to route: nothing rides in.
        if (! $team->hasDepartments) {
            $payload['department_ids'] = [];
        }

        $this->replace($this->editing() ? array_intersect_key($payload, array_flip(['whatsapp', 'department_ids'])) : $payload);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = TeamMemberForm::fieldRules($this->editing(), $this->user()->business_id);

        return $this->editing() ? array_intersect_key($rules, array_flip(['whatsapp', 'department_ids', 'department_ids.*'])) : $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return TeamMemberForm::fieldNames();
    }

    /**
     * A text box that arrives as a list or an object is read as nothing sent, not as a crash.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function boxes(array $input): array
    {
        return array_filter($input, fn (mixed $value, string|int $key): bool => ! in_array($key, ['name', 'email', 'whatsapp'], true) || ! is_array($value), ARRAY_FILTER_USE_BOTH);
    }

    private function editing(): bool
    {
        return $this->route('member') !== null;
    }

    private function team(): Team
    {
        $team = Client::for($this->user())->team;

        // EnsureAppAccess already refused anyone without a business.
        abort_if($team === null, 404);

        return $team;
    }
}
