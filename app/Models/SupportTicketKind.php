<?php

declare(strict_types=1);

namespace App\Models;

use App\Classes\Main\RowGlyph;
use App\Dto\CatalogColumnDto;
use App\Enums\CatalogCell;
use App\Enums\SupportTicketPriority;
use App\Interfaces\Catalog\DataTable;
use App\Interfaces\Catalog\HasColumns;
use App\Interfaces\Catalog\Removable;
use App\Traits\CountsUsage;
use App\Traits\EditedByForm;
use App\Traits\TracksUserActions;
use Database\Factories\SupportTicketKindFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * What a business is bringing to support: a problem, an idea, a question.
 *
 * A master and not an Enum: nothing in the code branches on which kind it is,
 * and the owner renames or adds them. Asked AFTER the description, never
 * before: a form that demands a category before letting someone write is the
 * first reason a ticket never gets sent.
 */
#[Fillable(['code', 'name', 'icon', 'starting_priority', 'runs_clock', 'sort_order', 'is_active'])]
class SupportTicketKind extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;
    use EditedByForm;

    /** @use HasFactory<SupportTicketKindFactory> */
    use HasFactory;

    // A kind is never really deleted: tickets filed under it keep its name.
    use SoftDeletes;
    use TracksUserActions;

    /** Tickets store the kind's `code` as text, which no foreign key can see. */
    public function usage(): array
    {
        return $this->onlyHeld(['support_tickets' => $this->heldBy('support_tickets', 'kind', $this->code)]);
    }

    /**
     * Resolved once per process: a ticket list asks for its kind on every row.
     *
     * @var array<string, self|null>
     */
    private static array $byCode = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starting_priority' => SupportTicketPriority::class,
            'runs_clock' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::$byCode = []);
        static::deleted(fn () => self::$byCode = []);
    }

    /** Trashed ones too: an old ticket still has to say what it was. */
    public static function byCode(?string $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return self::$byCode[$code] ??= self::query()->withTrashed()->where('code', $code)->first();
    }

    /**
     * What a report of this kind is offered under, switched on and in the
     * order she set. The widget and the admin filter read this one list.
     *
     * @return Collection<int, self>
     */
    public static function offered(): Collection
    {
        return self::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
    }

    /** The code a new report starts selected on: the first one offered. */
    public static function defaultCode(): string
    {
        return self::offered()->first()?->code ?? 'problem';
    }

    /**
     * Options for a combobox, in her order.
     *
     * @param  list<bool>  $states  Empty does NOT filter: an editor still has to
     *                              resolve the kind a saved row points at.
     * @return list<array{value: string, label: string}>
     */
    public static function options(array $states = []): array
    {
        return self::query()
            ->when($states !== [], fn ($query) => $query->whereIn('is_active', $states))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (self $kind): array => ['value' => $kind->code, 'label' => $kind->name])
            ->all();
    }

    public function label(): string
    {
        return $this->name;
    }

    /** A code is a key, not a title: lowercase, underscores, no accents. */
    public static function normalizeCode(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', str($value)->ascii()->lower()->toString()), '_');
    }

    /**
     * The glyphs of the registry a kind may wear, as combobox options.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function iconOptions(): array
    {
        return array_map(
            fn (string $name): array => ['value' => $name, 'label' => $name],
            self::iconNames(),
        );
    }

    /** @return list<string> */
    public static function iconNames(): array
    {
        return array_values(array_filter(array_keys((array) config('icons')), 'is_string'));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (self $kind): array => [
                'id' => $kind->id,
                'code' => $kind->code,
                'name' => $kind->name,
                'icon' => $kind->icon,
                // The glyph the web draws beside the name; the phone draws it from `icon`, by its key.
                ...RowGlyph::fields($kind->icon),
                'priority' => $kind->starting_priority->label(),
                'clock' => $kind->runs_clock ? __('catalog.support_ticket_kind.clock.runs') : __('catalog.support_ticket_kind.clock.none'),
                'sort_order' => $kind->sort_order,
                'active' => $kind->is_active,
            ])
            ->values();
    }

    /**
     * The priority and the clock travel as the web's words, so the phone never
     * spells them; the web sets all three middle columns in `catalog-cell-sym`.
     */
    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('code', __('catalog.support_ticket_kind.columns.code'), CatalogCell::Code, search: true),
            new CatalogColumnDto('name', __('catalog.support_ticket_kind.columns.name'), CatalogCell::Name, fill: true, search: true, icon: 'icon'),
            new CatalogColumnDto('priority', __('catalog.support_ticket_kind.columns.priority'), CatalogCell::Symbol),
            new CatalogColumnDto('clock', __('catalog.support_ticket_kind.columns.clock'), CatalogCell::Symbol),
            new CatalogColumnDto('sort_order', __('catalog.support_ticket_kind.columns.sort_order'), CatalogCell::Symbol),
            new CatalogColumnDto('active', __('catalog.support_ticket_kind.columns.status'), CatalogCell::Active, on: __('catalog.support_ticket_kind.status.active'), off: __('catalog.support_ticket_kind.status.inactive')),
        ];
    }
}
