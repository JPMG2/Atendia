<?php

declare(strict_types=1);

namespace App\Actions\Embeddings;

use App\Classes\Main\EmbeddingSpace;
use App\Classes\Main\EmbeddingTables;
use App\Enums\EmbeddingMigrationStatus;
use App\Models\EmbeddingMigration;
use App\Services\Knowledge\VectorColumns;
use DomainException;
use Illuminate\Support\Facades\DB;

class ActivateEmbeddingMigration
{
    public function __construct(private VectorColumns $columns) {}

    /**
     * Puts the new model in force, all tables at once. The tables are locked
     * and re-counted first: a row written since the screen last looked would
     * otherwise be left with a vector of the old space in the new one's place.
     *
     * @throws DomainException
     */
    public function handle(EmbeddingMigration $migration): void
    {
        if (! in_array($migration->status, [EmbeddingMigrationStatus::Building, EmbeddingMigrationStatus::Ready], true)) {
            throw new DomainException('not_activatable');
        }

        DB::transaction(function () use ($migration): void {
            $tables = EmbeddingTables::registered();

            $this->columns->lock($tables);

            foreach ($tables as $table) {
                if ($this->columns->pending($table) > 0) {
                    throw new DomainException('not_converted');
                }
            }

            foreach ($tables as $table) {
                $this->columns->switchIn($table);
            }

            $migration->update([
                'status' => EmbeddingMigrationStatus::Switched,
                'ready_at' => $migration->ready_at ?? now(),
                'switched_at' => now(),
            ]);
        });

        EmbeddingSpace::forget();
    }
}
