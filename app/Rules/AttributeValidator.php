<?php

declare(strict_types=1);

namespace App\Rules;

use Illuminate\Validation\Rule;

class AttributeValidator
{
    private const MAX_STRING_LENGTH = 255;

    private const XSS_PREVENTION_PATTERN = '/^([^<>]*)$/';

    private const DIGIT_PATTERN = '/^([0-9\s\-\+\(\)]*)$/';

    private const ALPHA_PATTERN = '/^[\p{L}\p{M}\s\'\-]+$/u';

    /**
     * Plain digits with an optional decimal part: no sign, no thousands separator.
     *
     * A thousands-separated branch (`1,500.50`) used to live here, but it was
     * dead: the companion `numeric` rule rejects that shape first, so it never
     * matched anything.
     */
    private const DECIMAL_PATTERN = '/^\d+(\.\d+)?$/';

    /**
     * Build a unique email rule (RFC + DNS), optionally ignoring an existing record id.
     *
     * @return array<int, string>
     */
    public static function uniqueEmail(string $model, string $uniqueField, ?int $id = null): array
    {
        return [
            'required',
            'email:rfc,dns',
            self::buildUniqueRule($model, $uniqueField, $id),
            'regex:'.self::XSS_PREVENTION_PATTERN,
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    public static function uniqueAlpha(bool $required, string $length, bool $allowSpaces, string $model, string $uniqueField, ?int $id = null): array
    {
        return [
            $required ? 'required' : 'sometimes',
            self::buildUniqueRule($model, $uniqueField, $id),
            'min:'.$length,
            $allowSpaces ? 'regex:'.self::ALPHA_PATTERN : 'alpha',
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    /**
     * Build a required, unique, length-bounded string rule (commonly used for names/codes).
     *
     * @return array<int, string>
     */
    public static function uniqueIdNameLength(string $length, string $model, string $uniqueField, ?int $id = null): array
    {
        return [
            'required',
            self::buildUniqueRule($model, $uniqueField, $id),
            'min:'.$length,
            'regex:'.self::XSS_PREVENTION_PATTERN,
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    /**
     * Build a digit-only string rule allowing common phone separators.
     *
     * @return array<int, string>
     */
    public static function digitValid(string $length, bool $required): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'min:'.$length,
            'regex:'.self::DIGIT_PATTERN,
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    /**
     * Build an optional unique email rule (RFC only).
     *
     * @return array<int, string>
     */
    public static function emailValid(string $model, string $uniqueField, ?int $id = null): array
    {
        return [
            'sometimes',
            self::buildUniqueRule($model, $uniqueField, $id),
            'email:rfc',
            'regex:'.self::XSS_PREVENTION_PATTERN,
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    /**
     * Build an optional unique email rule (RFC + DNS) tied to an existing record id.
     *
     * @return array<int, string>
     */
    public static function emailValidById(int $id, string $model, string $uniqueField): array
    {
        return [
            'sometimes',
            'email:rfc,dns',
            self::buildUniqueRule($model, $uniqueField, $id),
            'regex:'.self::XSS_PREVENTION_PATTERN,
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    /**
     * Build a length-bounded string rule, required or optional.
     *
     * @return array<int, string>
     */
    public static function stringValid(bool $required, string $length): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'min:'.$length,
            'regex:'.self::XSS_PREVENTION_PATTERN,
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    /**
     * @deprecated Duplicate of {@see self::booleanValue()}; kept as an alias so
     *             existing call sites keep working. Use `booleanValue()`.
     *
     * @return array<int, string>
     */
    public static function valueBoolean(bool $required): array
    {
        return self::booleanValue($required);
    }

    /**
     * Build an optional unique string rule.
     *
     * @return array<int, string>
     */
    public static function stringValidUnique(string $model, string $uniqueField, string $length, ?int $id = null): array
    {
        return [
            'sometimes',
            'min:'.$length,
            self::buildUniqueRule($model, $uniqueField, $id),
            'regex:'.self::XSS_PREVENTION_PATTERN,
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    /**
     * Build a URL rule that verifies the host responds, required or optional.
     *
     * @return array<int, string>
     */
    public static function webValid(bool $required): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'url',
            'active_url',
            'regex:'.self::XSS_PREVENTION_PATTERN,
            'max:'.self::MAX_STRING_LENGTH,
        ];
    }

    public static function mayorValid(): string
    {
        return 'gt:0';
    }

    /**
     * Build a `d-m-Y` date rule, required or optional.
     *
     * @return array<int, string>
     */
    public static function dateValid(bool $required): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'date_format:d-m-Y',
            'max:'.self::MAX_STRING_LENGTH,
            'regex:'.self::XSS_PREVENTION_PATTERN,
        ];
    }

    public static function hasTobeArray(string $length): string
    {
        return 'array|min:'.$length;
    }

    /**
     * Build an `exists` rule for foreign keys; when not required, excludes when the column is 0.
     *
     * The optional branch also checks `exists`: `exclude_if` already drops the
     * "none selected" value (0), so any other value must point to a real row.
     *
     * @return array<int, string>
     */
    public static function requireAndExists(string $model, string $uniqueField, string $column, bool $require = false): array
    {
        if ($require) {
            return ['required', 'integer', 'exists:'.$model.','.$uniqueField];
        }

        return ['nullable', 'exclude_if:'.$column.',0', 'integer', 'exists:'.$model.','.$uniqueField];
    }

    /**
     * @return array<int, string>
     */
    public static function booleanValue(bool $required): array
    {
        return [$required ? 'required' : 'sometimes', 'boolean'];
    }

    /**
     * @return array<int, string>
     */
    public static function dateAfther(bool $required, string $date): array
    {
        return [
            $required ? 'required' : 'sometimes',
            'date_format:d-m-Y',
            'after:'.$date,
        ];
    }

    /**
     * Build a decimal rule.
     *
     * The `numeric` rule is mandatory here: without it Laravel measures the
     * string length instead of the value, so companion rules such as `gt:0`
     * would compare the number of characters. For the same reason there is no
     * `max:MAX_STRING_LENGTH` — on a numeric attribute that would cap the
     * value at 255, not its length.
     *
     * @return array<int, string>
     */
    public static function numericDecimal(bool $required): array
    {
        if ($required) {
            return ['required', 'numeric', 'regex:'.self::DECIMAL_PATTERN];
        }

        return ['sometimes', 'nullable', 'numeric', 'regex:'.self::DECIMAL_PATTERN];
    }

    /**
     * Build an integer rule with a minimum value.
     *
     * There is no `max:MAX_STRING_LENGTH` here on purpose: on an attribute
     * validated as an integer, Laravel's `max` compares the VALUE, so it used
     * to reject any number above 255. Pass an explicit upper bound at the call
     * site when a field really needs one.
     *
     * There is no XSS regex either: `integer:strict` only lets a real int
     * through, and an int cannot carry `<` or `>`.
     *
     * @return array<int, string>
     */
    public static function numericInteger(bool $required, int $min): array
    {
        if ($required) {
            return [
                'required',
                'integer:strict',
                'min:'.$min,
            ];
        }

        return [
            'sometimes',
            'nullable',
            'integer:strict',
            'min:'.$min,
        ];
    }

    /**
     * Build an int-only money rule.
     *
     * Same reasoning as {@see self::numericInteger()}: `integer:strict` already
     * rules out anything that could carry markup, so no XSS regex is needed.
     *
     * @return array<int, string>
     */
    public static function moneyInteger(bool $required): array
    {
        if ($required) {
            return ['required', 'integer:strict'];
        }

        return ['sometimes', 'nullable', 'integer:strict'];
    }

    /**
     * Build a required, scoped unique rule using Laravel's Rule::unique builder.
     *
     * @return array<int, mixed>
     */
    public static function requiredExistModelRelation(string $model, string $uniqueField, string $columRelation, ?int $columValue, ?int $id): array
    {
        $unique = Rule::unique($model, $uniqueField)->where($columRelation, $columValue);

        if ($id !== null) {
            $unique->ignore($id);
        }

        return ['required', $unique, 'regex:'.self::XSS_PREVENTION_PATTERN];
    }

    /**
     * Compose Laravel's string-based `unique` rule, ignoring an existing record id when provided.
     */
    private static function buildUniqueRule(string $model, string $uniqueField, ?int $id): string
    {
        $rule = 'unique:'.$model.','.$uniqueField;

        return $id !== null ? $rule.','.$id : $rule;
    }
}
