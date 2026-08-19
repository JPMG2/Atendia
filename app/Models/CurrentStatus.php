<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CurrentStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class CurrentStatus extends Model
{
    /** @use HasFactory<CurrentStatusFactory> */
    use HasFactory;

    /**
     * Nombre propio ("En proceso"): se respeta lo que escribe el usuario, solo
     * se limpian espacios. La columna es UNIQUE, así que normalizar ANTES de
     * validar es lo que hace que el duplicado salga como error de campo y no
     * como un crash de Postgres.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }
}
