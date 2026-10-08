<?php

declare(strict_types=1);

namespace App\Actions\Embeddings;

use App\Classes\Main\EmbeddingTables;
use App\Enums\EmbeddingMigrationStatus;
use App\Models\EmbeddingMigration;
use App\Services\Knowledge\VectorColumns;
use DomainException;
use Illuminate\Support\Facades\DB;

class DiscardEmbeddingBackup
{
    public function __construct(private VectorColumns $columns) {}

    /**
     * Drops the old vectors for good. After this there is no way back, which
     * is why the screen asks first and why it is a step of its own.
     *
     * @throws DomainException
     */
    public function handle(EmbeddingMigration $migration): void
    {
        if ($migration->status !== EmbeddingMigrationStatus::Switched) {
            throw new DomainException('not_discardable');
        }

        DB::transaction(function () use ($migration): void {
            foreach (EmbeddingTables::registered() as $table) {
                $this->columns->drop($table, 'embedding_prev');
            }

            $migration->update(['status' => EmbeddingMigrationStatus::Finished, 'closed_at' => now()]);
        });
    }
}
