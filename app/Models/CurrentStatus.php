<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\CurrentStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['name', 'color'])]
class CurrentStatus extends Model implements DataTable
{
    /** @use HasFactory<CurrentStatusFactory> */
    use HasFactory;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * Paleta de un estado: se guarda la CLAVE de un token semántico, nunca un hex.
     *
     * El estado se pinta en TODO el programa, así que el color tiene que responder
     * al tema: `var(--danger)` es rojo en claro y un rojo legible en oscuro,
     * mientras que un `#F2555A` guardado en la base se ve igual en los dos y en
     * oscuro queda fuera de contraste. Los swatches viven acá, en PHP, no en el
     * Blade — el markup nunca escribe un color.
     *
     * @var array<int, string>
     */
    public const COLORS = ['success', 'info', 'warning', 'danger', 'brand', 'neutral'];

    public const DEFAULT_COLOR = 'neutral';

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

    /**
     * El `id` viaja siempre: es la única clave estable para editar. El `name` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return Collection<int, array{id: int, name: string, color: string}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $status): array => [
                    'id' => $status->id,
                    'name' => $status->name,
                    'color' => $status->color,
                ],
            )
            ->values();
    }
}
