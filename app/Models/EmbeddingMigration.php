<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmbeddingMigrationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt to move the knowledge search to another embedding model. */
#[Fillable(['model', 'provider', 'dimensions', 'from_model', 'from_dimensions', 'status', 'started_by', 'ready_at', 'switched_at', 'closed_at'])]
class EmbeddingMigration extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => EmbeddingMigrationStatus::class,
            'ready_at' => 'datetime',
            'switched_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /** The change in progress, if any: there is only ever one. */
    public static function open(): ?self
    {
        return self::query()
            ->whereIn('status', collect(EmbeddingMigrationStatus::cases())->filter->isOpen()->map->value->all())
            ->latest('id')
            ->first();
    }

    /** The newest change whose model is in force: it says what the base was made with. */
    public static function ruling(): ?self
    {
        return self::query()
            ->whereIn('status', collect(EmbeddingMigrationStatus::cases())->filter->rules()->map->value->all())
            ->latest('switched_at')
            ->latest('id')
            ->first();
    }

    /** The closed ones, newest first, for the history under the card. */
    public static function history(int $limit = 5): EloquentCollection
    {
        return self::query()->latest('id')->limit($limit)->get();
    }
}
