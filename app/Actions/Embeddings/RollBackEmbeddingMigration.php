<?php

declare(strict_types=1);

namespace App\Actions\Embeddings;

use App\Classes\Main\EmbeddingSpace;
use App\Classes\Main\EmbeddingTables;
use App\Enums\EmbeddingMigrationStatus;
use App\Jobs\ReembedSpace;
use App\Models\EmbeddingMigration;
use App\Services\Knowledge\VectorColumns;
use DomainException;
use Illuminate\Support\Facades\DB;

class RollBackEmbeddingMigration
{
    public function __construct(private VectorColumns $columns) {}

    /**
     * Goes back to the model that ruled before, while its vectors are still
     * kept. What was written under the new model has no vector in the old
     * space, so it is queued to get one.
     *
     * @throws DomainException
     */
    public function handle(EmbeddingMigration $migration): void
    {
        if ($migration->status !== EmbeddingMigrationStatus::Switched) {
            throw new DomainException('not_rollbackable');
        }

        DB::transaction(function () use ($migration): void {
            $tables = EmbeddingTables::registered();

            $this->columns->lock($tables);

            foreach ($tables as $table) {
                $this->columns->switchBack($table);
                $this->columns->drop($table, 'embedding_next');
            }

            $migration->update(['status' => EmbeddingMigrationStatus::RolledBack, 'closed_at' => now()]);
        });

        EmbeddingSpace::forget();

        foreach (EmbeddingTables::registered() as $table) {
            if ($this->columns->missing($table) > 0) {
                ReembedSpace::dispatch($migration->id, $table->table, ReembedSpace::RESTORE);
            }
        }
    }
}
