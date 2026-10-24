<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\Schedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The web's HoursForm, rule for rule: the same field rules and the same
 * overlap check, both owned by {@see Schedule}. Shifts come keyed by day
 * (0 = Sunday) so a list can never be read one day off.
 */
class SaveBusinessHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [...Schedule::rules(), 'week' => ['required', 'array']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Schedule::attributes();
    }

    /** Overlap only makes sense on times that already parsed, as on the web. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach (Schedule::overlapErrors($this->validated('week')) as $key => $message) {
                    $validator->errors()->add($key, $message);
                }
            },
        ];
    }
}
