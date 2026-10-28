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
use Database\Factories\SupportReplyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

#[Fillable(['name', 'body', 'is_active'])]
class SupportReply extends Model implements DataTable, HasColumns, Removable
{
    use CountsUsage;

    /** @use HasFactory<SupportReplyFactory> */
    use HasFactory;

    // A master row is never deleted: a ticket message may have quoted it.
    use SoftDeletes;
    use TracksUserActions;

    /** A canned reply is copied into a ticket, never linked. */
    public function usage(): array
    {
        return [];
    }

    /** The longest a reply can be: the composer stops at the same number. */
    public const BODY_MAX = 900;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** Spacing cleaned, nothing else: the UNIQUE column turns a duplicate into a field error. */
    public static function normalizeName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /** Line breaks are the writer's paragraphs; only the outer blanks go. */
    public static function normalizeBody(string $value): string
    {
        return trim($value);
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeName($value),
        );
    }

    protected function body(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => self::normalizeBody($value),
        );
    }

    /**
     * @return Collection<int, array{id: int, name: string, body: string, active: bool}>
     */
    public function catalogRows(): Collection
    {
        return $this->newQuery()
            ->orderBy('name')
            ->get()
            ->map(
                fn (self $reply): array => [
                    'id' => $reply->id,
                    'name' => $reply->name,
                    'body' => $reply->body,
                    'active' => $reply->is_active,
                ],
            )
            ->values();
    }

    /**
     * What the composer offers: the active ones, by name.
     *
     * @return array<int, string> id => name
     */
    public static function shelf(): array
    {
        return self::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** The text to paste for one reply, or null when it is gone or switched off. */
    public static function bodyOf(int $id): ?string
    {
        return self::query()->where('is_active', true)->whereKey($id)->value('body');
    }

    public function catalogColumns(): array
    {
        return [
            new CatalogColumnDto('name', __('catalog.support_reply.columns.name'), CatalogCell::Name, search: true),
            new CatalogColumnDto('body', __('catalog.support_reply.columns.body'), CatalogCell::Text, fill: true, search: true, copy: ['label' => __('catalog.support_reply.copy.label'), 'done' => __('catalog.support_reply.copy.done')]),
            new CatalogColumnDto('active', __('catalog.support_reply.columns.status'), CatalogCell::Active, on: __('catalog.support_reply.status.active'), off: __('catalog.support_reply.status.inactive')),
        ];
    }
}
