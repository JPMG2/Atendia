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

    // A master row is never deleted: whatever references it would dangle.
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
     * Network names are PROPER NAMES: "TikTok", "LinkedIn", "X (Twitter)". Same
     * criterion as the other masters — kept as typed, spacing cleaned.
     */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * A URL carries no spaces: ALL of them go, not only the outer ones. One
     * pasted along with it would either slip past Laravel's `url` rule or bounce
     * without the person seeing why.
     */
    public static function normalizeUrl(string $value): string
    {
        return (string) preg_replace('/\s+/u', '', $value);
    }

    /**
     * The icon is the KEY in config/icons.php, and the column is nullable: with
     * none picked, null is stored rather than ''.
     */
    public static function normalizeIcon(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', '', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * The short form is kept exactly as typed — same lesson as
     * Currency::normalizeSymbol: forcing uppercase would wreck one written on
     * purpose another way. The column is nullable, so empty means null.
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
     * The `id` always travels: it is the only stable key for editing. The `name`
     * is user-editable, so it cannot identify the row.
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
                    // The columns are nullable and Alpine paints the raw value: a null
                    // would read as "null" in the cell, so it travels empty.
                    'icon' => $network->icon ?? '',
                    'abbreviation' => $network->abbreviation ?? '',
                    'active' => $network->is_active,
                ],
            )
            ->values();
    }

    /**
     * An empty `states` does NOT filter, on purpose: the combobox resolves the
     * chosen option inside `options`, so hiding a deactivated row would blank the
     * field when editing a record that uses it.
     *
     * The bare name is the default: the short form is optional, so prefixing it
     * would leave half the list with a dangling dash.
     *
     * @param  list<bool>  $states  `is_active` values to include; empty = all
     * @param  (Closure(self): string)|null  $label  the option text; null = the default
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
