<?php

declare(strict_types=1);

namespace App\Models;

use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\Removable;
use App\Traits\CountsUsage;
use App\Traits\TracksUserActions;
use Database\Factories\CurrentStatusFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['name', 'color'])]
class CurrentStatus extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;

    /** @use HasFactory<CurrentStatusFactory> */
    use HasFactory;

    // A master row is never deleted: whatever references it would dangle.
    use SoftDeletes;
    use TracksUserActions;

    /** Nothing in the schema points at a status: it is a label the screens read. */
    public function usage(): array
    {
        return [];
    }

    /**
     * A status palette: what is stored is the KEY of a semantic token, never a hex.
     *
     * A status is painted all over the program, so its colour has to follow the
     * theme — a hex in the database looks the same in both and falls out of
     * contrast in dark. The swatches live here, in PHP: markup never writes a
     * colour.
     *
     * @var array<int, string>
     */
    public const COLORS = ['success', 'info', 'warning', 'danger', 'brand', 'neutral'];

    public const DEFAULT_COLOR = 'neutral';

    /**
     * A proper name, kept as typed with only the spacing cleaned. The column is
     * UNIQUE, so normalising BEFORE validating is what turns a duplicate into a
     * field error instead of a Postgres crash.
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
     * The `id` always travels: it is the only stable key for editing. The `name`
     * is user-editable, so it cannot identify the row.
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

    /** With two columns the colour takes the slack, so the tag stays beside the name. */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('name', __('catalog.status.columns.name'), CatalogCell::Name),
            new CatalogColumnDto('color', __('catalog.status.columns.color'), CatalogCell::Tag, fill: true),
        ];
    }
}
