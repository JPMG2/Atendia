<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\TracksUserActions;
use Database\Factories\BusinessSectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Rubro del negocio: Salud, Gastronomía, Belleza…
 *
 * Maestro que carga el admin. Agrupa {@see BusinessActivity}, que es el nivel
 * con el que trabaja el asistente.
 */
#[Fillable(['code', 'name', 'description', 'sort_order', 'is_active'])]
class BusinessSector extends Model
{
    /** @use HasFactory<BusinessSectorFactory> */
    use HasFactory;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * @return HasMany<BusinessActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(BusinessActivity::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nombre propio: se respeta lo que escribe el usuario, solo se limpian
     * espacios. Mismo criterio que Province::normalizeName.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * La clave es técnica, no copy: se guarda siempre en minúsculas.
     */
    public static function normalizeCode(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeCode($value),
        );
    }
}
