<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\ProvinceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['country_id', 'name', 'is_active'])]
class Province extends Model implements DataTable
{
    /** @use HasFactory<ProvinceFactory> */
    use HasFactory;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * País al que pertenece la provincia (FK obligatoria en la tabla).
     *
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'country_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Nombre propio: se respeta lo que escribe el usuario, solo se limpian
     * espacios. Mismo criterio que Country::normalizeName.
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

    /**
     * El `id` viaja siempre: es la única clave estable para editar. El `name` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return Collection<int, array{id: int, name: string, country: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->with('country:id,name')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $province): array => [
                    'id' => $province->id,
                    'name' => $province->name,
                    'country' => $province->country?->name ?? '',
                    'active' => $province->is_active,
                ],
            )
            ->values();
    }
}
