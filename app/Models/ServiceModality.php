<?php

declare(strict_types=1);

namespace App\Models;

use App\Interfaces\Catalog\DataTable;
use App\Traits\TracksUserActions;
use Database\Factories\ServiceModalityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * HOW a service is offered.
 *
 * A service type inherits exactly one. It decides what the assistant asks and
 * what the system remembers. The `code` is the hinge with the code.
 */
#[Fillable(['code', 'name', 'description', 'icon', 'sort_order', 'is_active'])]
class ServiceModality extends Model implements DataTable
{
    /** @use HasFactory<ServiceModalityFactory> */
    use HasFactory;

    use LogsActivity;

    // A master row is never deleted: the service types would dangle.
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
     * The service types inheriting this modality — many of them: a dish and a
     * combo share "product" without that breaking anything.
     *
     * @return HasMany<ServiceType, $this>
     */
    public function types(): HasMany
    {
        return $this->hasMany(ServiceType::class);
    }

    /** Whether a service type already uses it. If so it is deactivated, not deleted. */
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

    /** A proper name: kept as the admin typed it, only the spacing is cleaned. */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** The key is technical, not copy: always lowercase. */
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
     * The inside of the glyph's SVG, empty when there is no icon or the one set
     * is no longer in `config/icons.php`.
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
     * Sorted by `sort_order`: the order is the admin's, so the business sees them
     * that way when choosing.
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
                    // The SVG travels with the row so the cell paints the glyph and not
                    // its key: a key in a table is config, not a fact for whoever fills
                    // the catalog in.
                    'icon_svg' => self::iconSvg($modality->icon),
                    'order' => $modality->sort_order,
                    'active' => $modality->is_active,
                ],
            )
            ->values();
    }

    /**
     * An empty `states` does NOT filter, on purpose: the combobox resolves the
     * chosen option inside `options`, so hiding a deactivated row would blank the
     * field when editing a record that uses it.
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @return array<int, array{value: int, label: string}>
     */
    public static function options(array $states = []): array
    {
        /** @var Builder<self> $query */
        $query = self::query();

        if ($states !== []) {
            $query->whereIn('is_active', $states);
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $modality): array => [
                    'value' => $modality->id,
                    'label' => $modality->name,
                ],
            )
            ->all();
    }
}
