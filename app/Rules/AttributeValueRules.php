<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rule;

/**
 * Builds the validation for a type's attribute VALUES from its attribute
 * set: the definition decides the rule, so the sheet, the forms and the
 * catalog never repeat what a "money" or a "list" means. The caller must
 * hand every attribute of the set a key (null included), or `required`
 * would never see a field the screen simply skipped.
 */
class AttributeValueRules
{
    /**
     * @param  list<array{id: int, label: string, data_type: string, is_required: bool, is_multiple: bool, options: ?array<int, string>}>  $set
     * @return array<string, list<mixed>>
     */
    public static function rules(array $set): array
    {
        $rules = [];

        foreach ($set as $attribute) {
            $key = 'attribute_values.'.$attribute['id'];

            $rules[$key] = [
                $attribute['is_required'] ? 'required' : 'nullable',
                ...self::forDataType($attribute),
            ];

            if ($attribute['data_type'] === 'list' && $attribute['is_multiple']) {
                $rules[$key.'.*'] = [Rule::in($attribute['options'] ?? [])];
            }
        }

        return $rules;
    }

    /**
     * @param  list<array{id: int, label: string}>  $set
     * @return array<string, string>
     */
    public static function attributes(array $set): array
    {
        return collect($set)
            ->mapWithKeys(fn (array $attribute): array => ['attribute_values.'.$attribute['id'] => $attribute['label']])
            ->all();
    }

    /**
     * Shapes the raw sheet values against the set: EVERY attribute gets its
     * key (null included, so `required` can see it), text is squished, a
     * boolean becomes one and a multiple list a clean array.
     *
     * @param  list<array{id: int, data_type: string, is_multiple: bool}>  $set
     * @param  array<int|string, mixed>  $raw
     * @return array<int, mixed>
     */
    public static function normalize(array $set, array $raw): array
    {
        $values = [];

        foreach ($set as $attribute) {
            $value = $raw[$attribute['id']] ?? $raw[(string) $attribute['id']] ?? null;

            $values[$attribute['id']] = match (true) {
                $value === null || $value === '' || $value === [] => null,
                $attribute['data_type'] === 'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
                $attribute['data_type'] === 'list' && $attribute['is_multiple'] => array_values(array_filter((array) $value, fn (mixed $option): bool => $option !== null && $option !== '')),
                is_string($value) => trim((string) preg_replace('/\s+/u', ' ', $value)) ?: null,
                default => $value,
            };
        }

        return $values;
    }

    /**
     * What actually gets stored: the empty keys validation needed are noise
     * on the row. A false stays — "no" is an answer, not an absence.
     *
     * @param  array<int|string, mixed>  $values
     * @return ?array<int, mixed>
     */
    public static function strip(array $values): ?array
    {
        $kept = array_filter($values, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        return $kept === [] ? null : $kept;
    }

    /**
     * @param  array{data_type: string, is_multiple: bool, options: ?array<int, string>}  $attribute
     * @return list<mixed>
     */
    private static function forDataType(array $attribute): array
    {
        return match ($attribute['data_type']) {
            'number' => ['numeric'],
            'money' => ['numeric', 'min:0', AttributeValidator::plainDecimal()],
            'boolean' => ['boolean'],
            'date' => ['date_format:d/m/Y'],
            'time' => ['date_format:H:i'],
            'list' => $attribute['is_multiple']
                ? ['array']
                : [Rule::in($attribute['options'] ?? [])],
            default => ['string', 'max:255', AttributeValidator::xssFree()],
        };
    }
}
