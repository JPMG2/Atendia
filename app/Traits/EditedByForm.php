<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * The record a form is about to load or save, asked of the model and never
 * built inside the form. Global scopes still apply: a tenant model hands back
 * nothing for another business's id.
 *
 * @mixin Model
 */
trait EditedByForm
{
    public static function editable(int $id): ?static
    {
        return static::query()->find($id);
    }

    public static function editableOrFail(int $id): static
    {
        return static::query()->findOrFail($id);
    }
}
