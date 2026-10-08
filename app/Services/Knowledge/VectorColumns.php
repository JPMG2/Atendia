<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Dto\EmbeddingTableDto;
use Illuminate\Support\Facades\DB;

/**
 * The columns a change of embedding model works on: `embedding_next` is filled
 * beside `embedding`, and switching renames the pair, so no query has to know.
 * The old column waits as `embedding_prev` until it is discarded. Names come
 * from `EmbeddingTables`, never a request, so they can be written into SQL.
 */
class VectorColumns
{
    /** Next to the live column: the new space, empty until the job fills it. */
    public function addNext(EmbeddingTableDto $table, int $dimensions): void
    {
        $name = $table->table;

        DB::statement("ALTER TABLE {$name} ADD COLUMN embedding_next vector({$dimensions})");

        // Chunks were born NOT NULL; the column that will be left behind must
        // accept rows written after the switch, which carry no vector in it.
        DB::statement("ALTER TABLE {$name} ALTER COLUMN embedding DROP NOT NULL");

        if ($table->indexed) {
            DB::statement("CREATE INDEX {$name}_embedding_next_vectorindex ON {$name} USING hnsw (embedding_next vector_cosine_ops)");
        }
    }

    /** Drops a column and, with it, its index. */
    public function drop(EmbeddingTableDto $table, string $column): void
    {
        DB::statement("ALTER TABLE {$table->table} DROP COLUMN IF EXISTS {$column}");
    }

    /** The new vectors take the live name; the old ones step aside. */
    public function switchIn(EmbeddingTableDto $table): void
    {
        $this->swap($table, 'embedding', 'embedding_prev', 'embedding_next', 'embedding');
    }

    /** The reverse: the old vectors come back and the new ones step aside to be dropped. */
    public function switchBack(EmbeddingTableDto $table): void
    {
        $this->swap($table, 'embedding', 'embedding_next', 'embedding_prev', 'embedding');
    }

    /**
     * @param  string  $live  The live column now.
     * @param  string  $liveTo  What the live one is renamed to.
     * @param  string  $other  The column that takes the live name.
     * @param  string  $otherTo  The name it takes.
     */
    private function swap(EmbeddingTableDto $table, string $live, string $liveTo, string $other, string $otherTo): void
    {
        $name = $table->table;

        DB::statement("ALTER TABLE {$name} RENAME COLUMN {$live} TO {$liveTo}");
        DB::statement("ALTER TABLE {$name} RENAME COLUMN {$other} TO {$otherTo}");

        // An index keeps its name when its column is renamed: the names follow,
        // or the next change would try to create one that is already taken.
        DB::statement("ALTER INDEX IF EXISTS {$name}_{$live}_vectorindex RENAME TO {$name}_{$liveTo}_vectorindex");
        DB::statement("ALTER INDEX IF EXISTS {$name}_{$other}_vectorindex RENAME TO {$name}_{$otherTo}_vectorindex");
    }

    /** @param  list<EmbeddingTableDto>  $tables */
    public function lock(array $tables): void
    {
        $names = implode(', ', array_map(fn (EmbeddingTableDto $table): string => $table->table, $tables));

        DB::statement("LOCK TABLE {$names} IN ACCESS EXCLUSIVE MODE");
    }

    /** Rows with a vector in the live column that still have none in the new one. */
    public function pending(EmbeddingTableDto $table): int
    {
        return DB::table($table->table)->whereNotNull('embedding')->whereNull('embedding_next')->count();
    }

    /** Rows with a vector in the live column: what has to be converted. */
    public function total(EmbeddingTableDto $table): int
    {
        return DB::table($table->table)->whereNotNull('embedding')->count();
    }

    /** Rows that already have their new vector. */
    public function converted(EmbeddingTableDto $table): int
    {
        return DB::table($table->table)->whereNotNull('embedding_next')->count();
    }

    /** Rows with no vector at all in the live column. */
    public function missing(EmbeddingTableDto $table): int
    {
        return DB::table($table->table)->whereNull('embedding')->count();
    }
}
