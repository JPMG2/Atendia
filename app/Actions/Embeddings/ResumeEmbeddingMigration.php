<?php

declare(strict_types=1);

namespace App\Actions\Embeddings;

use App\Classes\Main\EmbeddingTables;
use App\Enums\EmbeddingMigrationStatus;
use App\Jobs\ReembedSpace;
use App\Models\EmbeddingMigration;
use App\Services\Knowledge\VectorColumns;
use DomainException;

class ResumeEmbeddingMigration
{
    public function __construct(private VectorColumns $columns) {}

    /**
     * Asks again for what is still unconverted: after a failure, or after rows
     * arrived while the change was waiting to be switched on.
     *
     * @throws DomainException
     */
    public function handle(EmbeddingMigration $migration): int
    {
        if (! in_array($migration->status, [EmbeddingMigrationStatus::Building, EmbeddingMigrationStatus::Ready], true)) {
            throw new DomainException('not_resumable');
        }

        $pending = collect(EmbeddingTables::registered())
            ->mapWithKeys(fn ($table): array => [$table->table => $this->columns->pending($table)])
            ->filter();

        // The status first: a job that finishes at once settles it to ready, and
        // writing "building" over that would leave the change waiting forever.
        // New rows after "ready" make it unready again, and the screen must say so.
        $migration->update($pending->isNotEmpty()
            ? ['status' => EmbeddingMigrationStatus::Building, 'ready_at' => null]
            : ['status' => EmbeddingMigrationStatus::Ready, 'ready_at' => $migration->ready_at ?? now()]);

        foreach ($pending->keys() as $table) {
            ReembedSpace::dispatch($migration->id, $table);
        }

        return (int) $pending->sum();
    }
}
