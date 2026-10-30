<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

use App\Livewire\Forms\Catalog\CatalogWiring;

/**
 * A catalog master the phone edits with the web's own rules and Actions: its
 * Form says them once, statically, and both the web form and the API read them.
 */
interface EditableFromPhone
{
    public static function wiring(): CatalogWiring;

    /**
     * @param  array<string, mixed>  $data  What is being saved: a rule may hang off another field (a name unique per country).
     * @return array<string, mixed>
     */
    public static function fieldRules(?int $excludeId = null, array $data = []): array;

    /** @return array<string, string> */
    public static function fieldNames(): array;

    /**
     * The fields the web form draws, in its order and with its words; the
     * value is laid in by `CatalogEditor`.
     *
     * @return list<array{key: string, type: string, label: string, hint?: string, placeholder?: string, required?: bool, min?: int, max?: int, mono?: bool, options?: list<array{value: int|string|bool, label: string}>, on_label?: string, off_label?: string}>
     */
    public static function phoneFields(): array;
}
