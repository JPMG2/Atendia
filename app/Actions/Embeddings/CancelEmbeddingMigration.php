<?php

declare(strict_types=1);

namespace App\Actions\Embeddings;

use App\Classes\Main\EmbeddingTables;
use App\Enums\EmbeddingMigrationStatus;
use App\Models\EmbeddingMigration;
use App\Services\Knowledge\VectorColumns;
use DomainException;
use Illuminate\Support\Facades\DB;

class CancelEmbeddingMigration
{
    public function __construct(private VectorColumns $columns) {}

    /**
     * Gives up before switching: the new vectors are dropped and the model in
     * force never stopped being the old one, so nothing else changes.
     *
     * @throws DomainException
     */
    public function handle(EmbeddingMigration $migration): void
    {
        if (! in_array($migration->status, [EmbeddingMigrationStatus::Building, EmbeddingMigrationStatus::Ready], true)) {
            throw new DomainException('not_cancellable');
        }

        // The status goes first: a job still running sees it and stops writing.
        $migration->update(['status' => EmbeddingMigrationStatus::Cancelled, 'closed_at' => now()]);

        DB::transaction(function (): void {
            foreach (EmbeddingTables::registered() as $table) {
                $this->columns->drop($table, 'embedding_next');
            }
        });
    }
}
