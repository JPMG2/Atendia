<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Classes\Main\Closures;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The web's ClosuresForm, rule for rule: both validate with {@see Closures},
 * so what the hours card refuses the app refuses too, in the same words.
 */
class AddBusinessClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return Closures::rules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return Closures::attributes();
    }
}
