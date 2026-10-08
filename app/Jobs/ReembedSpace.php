<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Classes\Main\EmbeddingSpace;
use App\Classes\Main\EmbeddingTables;
use App\Dto\EmbeddingTableDto;
use App\Enums\EmbeddingMigrationStatus;
use App\Models\EmbeddingMigration;
use App\Services\Knowledge\KnowledgeEmbedder;
use App\Services\Knowledge\VectorColumns;
use App\Services\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Makes the vectors of ONE table for a change of embedding model, a few
 * batches at a time and then asking for itself again: a long table never
 * holds a worker past its timeout, and a failure loses one batch, not the run.
 *
 * `next` fills the new column from the live one. `restore` runs after going
 * back, and gives a vector to what was written while the new model ruled.
 */
class ReembedSpace implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public const string NEXT = 'next';

    public const string RESTORE = 'restore';

    private const int BATCH = 100;

    private const int BATCHES_PER_RUN = 5;

    public int $tries = 3;

    public int $timeout = 300;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public int $migrationId, public string $table, public string $mode = self::NEXT) {}

    public function uniqueId(): string
    {
        return "{$this->migrationId}:{$this->table}:{$this->mode}";
    }

    public function handle(KnowledgeEmbedder $embedder, VectorColumns $columns): void
    {
        $migration = EmbeddingMigration::query()->find($this->migrationId);
        $table = EmbeddingTables::named($this->table);

        if ($migration === null || $table === null || ! $this->isStillWanted($migration)) {
            return;
        }

        // Platform-wide work: every business's rows, so no tenant is in force.
        // Naming it keeps a worker that served a business earlier from fencing us.
        app(Tenant::class)->for(null, function () use ($migration, $table, $embedder, $columns): void {
            $space = $this->mode === self::NEXT
                ? new EmbeddingSpace($migration->model, $migration->provider, $migration->dimensions)
                : EmbeddingSpace::active();
            $target = $this->mode === self::NEXT ? 'embedding_next' : 'embedding';

            for ($batch = 0; $batch < self::BATCHES_PER_RUN; $batch++) {
                if (! $this->convertBatch($table, $embedder, $space, $target)) {
                    break;
                }
            }

            if ($this->hasMoreToConvert($table)) {
                self::dispatch($this->migrationId, $this->table, $this->mode);

                return;
            }

            if ($this->mode === self::NEXT) {
                $this->settle($migration, $columns);
            }
        });
    }

    /** A cancelled or switched change must not keep writing into columns that are gone or renamed. */
    private function isStillWanted(EmbeddingMigration $migration): bool
    {
        return $this->mode === self::NEXT
            ? $migration->status === EmbeddingMigrationStatus::Building
            : $migration->status === EmbeddingMigrationStatus::RolledBack;
    }

    /** One batch; false when there was nothing left to do. */
    private function convertBatch(EmbeddingTableDto $table, KnowledgeEmbedder $embedder, EmbeddingSpace $space, string $target): bool
    {
        $rows = $this->pendingQuery($table)->select(['id', ...$table->columns])->orderBy('id')->limit(self::BATCH)->get();

        if ($rows->isEmpty()) {
            return false;
        }

        // The API refuses an empty string, and a row with no text is no reason to stall the run.
        $texts = $rows->map(fn (object $row): string => trim(($table->text)($row)) === '' ? '-' : ($table->text)($row))->all();
        $vectors = $embedder->embed($texts, $space);

        if (count($vectors) !== $rows->count()) {
            throw new RuntimeException("Embedding of {$table->table} returned ".count($vectors).' vectors for '.$rows->count().' rows.');
        }

        foreach ($rows->values() as $index => $row) {
            // A raw update: a vector is no edit, so no audit, observer or re-sync.
            DB::table($table->table)->where('id', $row->id)->update([$target => json_encode($vectors[$index])]);
        }

        return true;
    }

    private function pendingQuery(EmbeddingTableDto $table): Builder
    {
        $query = DB::table($table->table);

        return $this->mode === self::NEXT
            ? $query->whereNotNull('embedding')->whereNull('embedding_next')
            : $query->whereNull('embedding');
    }

    private function hasMoreToConvert(EmbeddingTableDto $table): bool
    {
        return $this->pendingQuery($table)->exists();
    }

    /** The last table to finish is the one that makes the whole change ready. */
    private function settle(EmbeddingMigration $migration, VectorColumns $columns): void
    {
        foreach (EmbeddingTables::registered() as $table) {
            if ($columns->pending($table) > 0) {
                return;
            }
        }

        EmbeddingMigration::query()
            ->whereKey($migration->id)
            ->where('status', EmbeddingMigrationStatus::Building)
            ->update(['status' => EmbeddingMigrationStatus::Ready, 'ready_at' => now()]);
    }
}
