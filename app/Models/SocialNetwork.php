<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Closure;
use Database\Factories\SocialNetworkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['name', 'url', 'icon', 'abbreviation', 'is_active'])]
class SocialNetwork extends Model implements DataTable
{
    /** @use HasFactory<SocialNetworkFactory> */
    use HasFactory;

    // Un maestro no se borra: lo que lo referencia quedaría colgando.
    use SoftDeletes;
    use TracksUserActions;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Los nombres de red son NOMBRES PROPIOS: "TikTok", "LinkedIn", "X (Twitter)".
     * Mismo criterio que Currency::normalizeName y Country::normalizeName: se
     * respeta lo que escribe el usuario, solo se limpian espacios.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * Una URL no lleva espacios: se quitan TODOS, no solo los de las puntas. Un
     * espacio pegado al copiar ("https://x.com/ ") pasaría el `url` de Laravel
     * como falso positivo o rebotaría sin que el usuario vea por qué.
     */
    public static function normalizeUrl(string $value): string
    {
        return (string) preg_replace('/\s+/u', '', $value);
    }

    /**
     * El ícono es la CLAVE de config/icons.php ("x-twitter"), y la columna es
     * nullable: sin ícono elegido se guarda null, no ''.
     */
    public static function normalizeIcon(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', '', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * La abreviatura se respeta tal cual la escribe el usuario — misma lección que
     * Currency::normalizeSymbol: forzar mayúsculas acá rompería una abreviatura
     * que el usuario quiso escribir de otra forma. Solo se limpian espacios, y la
     * columna es nullable, así que vacío es null.
     */
    public static function normalizeAbbreviation(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }

    protected function url(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeUrl($value),
        );
    }

    protected function icon(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizeIcon($value),
        );
    }

    protected function abbreviation(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => self::normalizeAbbreviation($value),
        );
    }

    /**
     * El `id` viaja siempre: es la única clave estable para editar. El `name` es
     * editable por el usuario, así que no sirve para identificar la fila.
     *
     * @return Collection<int, array{id: int, name: string, url: string, icon: string, abbreviation: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $network): array => [
                    'id' => $network->id,
                    'name' => $network->name,
                    'url' => $network->url,
                    // Las columnas son nullable y Alpine pinta el valor crudo: un
                    // null saldría como "null" en la celda, así que viaja vacío.
                    'icon' => $network->icon ?? '',
                    'abbreviation' => $network->abbreviation ?? '',
                    'active' => $network->is_active,
                ],
            )
            ->values();
    }

    /**
     * Opciones para el combobox de red social.
     *
     * Un array vacío NO filtra: trae el catálogo completo. Ese es el default
     * porque el combobox resuelve la opción elegida buscando su id dentro de
     * `options` (resources/js/combobox.js): si una fila dada de baja quedara
     * fuera, editar un registro que la referencia mostraría el campo vacío.
     *
     * El default es el nombre pelado ("Instagram"): la abreviatura es opcional
     * en la tabla, así que anteponerla dejaría la mitad de la lista con un
     * guión colgando. Una pantalla que la quiera pasa su propio `label`.
     *
     * @param  list<bool>  $states  estados de `is_active` a incluir; vacío = todos
     * @param  (Closure(self): string)|null  $label  texto de la opción; null = el default
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(array $states = [], ?Closure $label = null): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        $label ??= fn (self $network): string => $network->name;

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $network): array => [
                    'value' => $network->id,
                    'label' => $label($network),
                ],
            )
            ->all();
    }
}
