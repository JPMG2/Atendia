<?php

declare(strict_types=1);

use App\Rules\AttributeValidator;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| numericInteger — `max` on an integer caps the value, not the length
|--------------------------------------------------------------------------
*/

test('numericInteger accepts values above the string length limit', function () {
    $validator = Validator::make(
        ['quantity' => 1000],
        ['quantity' => AttributeValidator::numericInteger(true, 1)],
    );

    expect($validator->passes())->toBeTrue();
});

test('numericInteger no longer carries a value cap disguised as a length cap', function () {
    expect(AttributeValidator::numericInteger(true, 1))->not->toContain('max:255')
        ->and(AttributeValidator::numericInteger(false, 1))->not->toContain('max:255');
});

test('numericInteger still enforces its minimum value', function () {
    $validator = Validator::make(
        ['quantity' => 0],
        ['quantity' => AttributeValidator::numericInteger(true, 1)],
    );

    expect($validator->fails())->toBeTrue();
});

test('numericInteger skips a null value when it is optional', function () {
    $validator = Validator::make(
        ['quantity' => null],
        ['quantity' => AttributeValidator::numericInteger(false, 1)],
    );

    expect($validator->passes())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| integer:strict — the contract: callers must hand over a real int
|--------------------------------------------------------------------------
| `integer:strict` rejects a numeric STRING. Every call site must feed it an
| int: a typed `int`/`?int` Livewire property, or a cast/`(int)` on a raw HTTP
| request. A `?string` property bound to the field will fail validation even
| when the user typed a valid number.
*/

test('numericInteger accepts a real int', function () {
    $validator = Validator::make(
        ['quantity' => 5],
        ['quantity' => AttributeValidator::numericInteger(true, 1)],
    );

    expect($validator->passes())->toBeTrue();
});

test('numericInteger rejects a numeric string, so callers must cast first', function (string $value) {
    $validator = Validator::make(
        ['quantity' => $value],
        ['quantity' => AttributeValidator::numericInteger(true, 1)],
    );

    expect($validator->fails())->toBeTrue();
})->with(['5', '05', '5.0']);

test('int-only helpers drop the XSS regex that integer:strict makes redundant', function (array $rules) {
    expect($rules)->not->toContain('regex:/^([^<>]*)$/');
})->with([
    fn () => AttributeValidator::numericInteger(true, 1),
    fn () => AttributeValidator::numericInteger(false, 1),
    fn () => AttributeValidator::moneyInteger(true),
    fn () => AttributeValidator::moneyInteger(false),
]);

test('numericInteger rejects markup because it is not an int', function () {
    $validator = Validator::make(
        ['quantity' => '<script>alert(1)</script>'],
        ['quantity' => AttributeValidator::numericInteger(true, 1)],
    );

    expect($validator->fails())->toBeTrue();
});

test('moneyInteger rejects markup because it is not an int', function () {
    $validator = Validator::make(
        ['amount' => '<b>1500</b>'],
        ['amount' => AttributeValidator::moneyInteger(true)],
    );

    expect($validator->fails())->toBeTrue();
});

test('moneyInteger holds the same int-only contract', function () {
    $asInt = Validator::make(['amount' => 1500], ['amount' => AttributeValidator::moneyInteger(true)]);
    $asString = Validator::make(['amount' => '1500'], ['amount' => AttributeValidator::moneyInteger(true)]);

    expect($asInt->passes())->toBeTrue()
        ->and($asString->fails())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| numericDecimal — companion rules must compare the value, not the length
|--------------------------------------------------------------------------
*/

test('numericDecimal makes gt:0 compare the value instead of the string length', function () {
    $rules = [...AttributeValidator::numericDecimal(true), AttributeValidator::mayorValid()];

    $validator = Validator::make(['price' => '0.00'], ['price' => $rules]);

    expect($validator->fails())->toBeTrue();
});

test('numericDecimal accepts a positive decimal alongside gt:0', function () {
    $rules = [...AttributeValidator::numericDecimal(true), AttributeValidator::mayorValid()];

    $validator = Validator::make(['price' => '1500.50'], ['price' => $rules]);

    expect($validator->passes())->toBeTrue();
});

test('numericDecimal accepts values above the string length limit', function () {
    $validator = Validator::make(
        ['price' => '1000.00'],
        ['price' => AttributeValidator::numericDecimal(true)],
    );

    expect($validator->passes())->toBeTrue();
});

test('numericDecimal rejects a non numeric value', function () {
    $validator = Validator::make(
        ['price' => 'abc'],
        ['price' => AttributeValidator::numericDecimal(true)],
    );

    expect($validator->fails())->toBeTrue();
});

test('numericDecimal rejects the shapes its pattern never accepted', function (string $value) {
    $validator = Validator::make(
        ['price' => $value],
        ['price' => AttributeValidator::numericDecimal(true)],
    );

    expect($validator->fails())->toBeTrue();
})->with([
    '1,500.50',  // thousands separator: the removed dead branch, still rejected by `numeric`
    '-5.00',     // negative: the pattern carries no sign
    '1e5',       // exponent notation
]);

/*
|--------------------------------------------------------------------------
| requireAndExists — the optional branch must still check the foreign key
|--------------------------------------------------------------------------
*/

test('requireAndExists checks the foreign key when the field is optional', function () {
    $rules = AttributeValidator::requireAndExists('categories', 'id', 'category_id');

    expect($rules)->toContain('nullable')
        ->and($rules)->toContain('exclude_if:category_id,0')
        ->and($rules)->toContain('integer')
        ->and($rules)->toContain('exists:categories,id');
});

test('requireAndExists checks the foreign key when the field is required', function () {
    expect(AttributeValidator::requireAndExists('categories', 'id', 'category_id', true))
        ->toBe(['required', 'integer', 'exists:categories,id']);
});

/*
|--------------------------------------------------------------------------
| valueBoolean / booleanValue — one implementation, not two
|--------------------------------------------------------------------------
*/

test('valueBoolean is an alias of booleanValue', function (bool $required) {
    expect(AttributeValidator::valueBoolean($required))
        ->toBe(AttributeValidator::booleanValue($required));
})->with([true, false]);
