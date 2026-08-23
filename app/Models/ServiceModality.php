<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\ServiceModalityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Modalidad: CÓMO se ofrece un servicio.
 *
 * Un tipo de servicio hereda una sola. Es lo que decide qué pregunta el
 * asistente y qué recuerda el sistema. El `code` es la bisagra con el código.
 */
#[Fillable(['code', 'name', 'description', 'icon', 'sort_order', 'is_active'])]
class ServiceModality extends Model implements DataTable
{
    /** @use HasFactory<ServiceModalityFactory> */
    use HasFactory;

    use LogsActivity;

    // Un maestro no se borra: los tipos de servicio quedarían colgando.
    use SoftDeletes;
    use TracksUserActions;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['code', 'name', 'description', 'icon', 'sort_order', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('catalog');
    }

    /**
     * Los tipos de servicio que heredan esta modalidad. Son muchos: Plato y
     * Combo comparten "Producto" sin que eso rompa nada.
     *
     * @return HasMany<ServiceType, $this>
     */
    public function types(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    /** ¿Algún tipo de servicio ya la usa? Si sí, no se borra: se desactiva. */
    public function isInUse(): bool
    {
        return $this->types()->exists();
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

    /** Nombre propio: se respeta lo que escribe el admin, solo se limpian espacios. */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** La clave es técnica, no copy: siempre en minúsculas. */
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

    /**
     * El interior del SVG del glifo, o vacío si la modalidad no tiene ícono o si
     * el que tiene ya no está en `config/icons.php`.
     */
    private static function iconSvg(?string $icon): string
    {
        if ($icon === null) {
            return '';
        }

        $svg = config("icons.{$icon}");

        return is_string($svg) ? $svg : '';
    }

    /**
     * Se ordenan por `sort_order`: el orden es el que el admin decide para que el
     * negocio las vea así al elegir.
     *
     * @return Collection<int, array{id: int, code: string, name: string, description: string, icon: string, icon_svg: string, order: int, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $modality): array => [
                    'id' => $modality->id,
                    'code' => $modality->code,
                    'name' => $modality->name,
                    'description' => $modality->description ?? '',
                    'icon' => $modality->icon ?? '',
                    // El SVG viaja con la fila para que la celda pinte el glifo y
                    // no su clave: "calendar-check" en una tabla es config, no un
                    // dato para quien configura el catálogo. Alpine lo inyecta con
                    // x-html dentro de un <svg> que ya trae el trazo de Lucide.
                    'icon_svg' => self::iconSvg($modality->icon),
                    'order' => $modality->sort_order,
                    'active' => $modality->is_active,
                ],
            )
            ->values();
    }
}
