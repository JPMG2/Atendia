<?php

declare(strict_types=1);

namespace App\Actions\Embeddings;

use App\Classes\Main\EmbeddingSpace;
use App\Classes\Main\EmbeddingTables;
use App\Enums\AiCapability;
use App\Enums\EmbeddingMigrationStatus;
use App\Jobs\ReembedSpace;
use App\Models\AiModel;
use App\Models\EmbeddingMigration;
use App\Models\User;
use App\Services\Knowledge\VectorColumns;
use DomainException;
use Illuminate\Support\Facades\DB;

class StartEmbeddingMigration
{
    public function __construct(private VectorColumns $columns) {}

    /**
     * Opens the new vector column on every table and queues the work that
     * fills it. The assistant keeps searching with the model in force until
     * the change is switched on.
     *
     * @throws DomainException With the reason as its message: a key of `admin.ai.embeddings.errors`.
     */
    public function handle(string $model, int $dimensions, ?User $by = null): EmbeddingMigration
    {
        if (EmbeddingMigration::open() !== null) {
            throw new DomainException('already_open');
        }

        $choice = AiModel::query()
            ->where('capability', AiCapability::Embedding)
            ->where('is_active', true)
            ->where('code', $model)
            ->where('dimensions', $dimensions)
            ->latest('effective_from')
            ->first();

        if ($choice === null) {
            throw new DomainException('unknown_model');
        }

        $active = EmbeddingSpace::active();

        if ($active->model === $model && $active->dimensions === $dimensions) {
            throw new DomainException('same_as_active');
        }

        $migration = DB::transaction(function () use ($choice, $active, $by): EmbeddingMigration {
            $migration = EmbeddingMigration::query()->create([
                'model' => $choice->code,
                'provider' => $choice->provider,
                'dimensions' => (int) $choice->dimensions,
                'from_model' => $active->model,
                'from_dimensions' => $active->dimensions,
                'status' => EmbeddingMigrationStatus::Building,
                'started_by' => $by?->id,
            ]);

            foreach (EmbeddingTables::registered() as $table) {
                $this->columns->addNext($table, (int) $choice->dimensions);
            }

            return $migration;
        });

        foreach (EmbeddingTables::registered() as $table) {
            ReembedSpace::dispatch($migration->id, $table->table);
        }

        // Nothing to convert anywhere (an empty base) is already ready.
        $this->readyIfNothingPending($migration);

        return $migration->refresh();
    }

    private function readyIfNothingPending(EmbeddingMigration $migration): void
    {
        foreach (EmbeddingTables::registered() as $table) {
            if ($this->columns->pending($table) > 0) {
                return;
            }
        }

        $migration->update(['status' => EmbeddingMigrationStatus::Ready, 'ready_at' => now()]);
    }
}
