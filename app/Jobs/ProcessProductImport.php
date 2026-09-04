<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\KnowledgeDocument;
use App\Models\ProductImport;
use App\Services\ProductImport\ImportFileReader;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Materializes a confirmed spreadsheet import: the mapped core columns become
 * products (upsert by name — an import never deletes), and EVERY column
 * travels verbatim into one knowledge document per file, so the assistant can
 * answer what no schema anticipated. On failure it marks `failed` and
 * rethrows so the queue retries.
 */
class ProcessProductImport implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $importId) {}

    public function handle(ImportFileReader $reader): void
    {
        $import = ProductImport::query()->find($this->importId);

        if ($import === null || $import->status === 'done') {
            return;
        }

        $import->forceFill(['status' => 'processing'])->save();

        try {
            $rows = $reader->rows(Storage::disk('local')->path($import->path));

            $this->upsertProducts($import, $rows);
            $this->feedKnowledge($import, $rows);

            $import->forceFill(['status' => 'done', 'total_rows' => count($rows)])->save();
        } catch (Throwable $e) {
            $import->forceFill(['status' => 'failed'])->save();

            throw $e;
        }
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function upsertProducts(ProductImport $import, array $rows): void
    {
        $targets = array_column($import->mapping, 'target');
        $nameIndex = array_search('name', $targets, true);

        // Every target may have been steered to extra on the review screen.
        // No name column means no products — the knowledge still gets it all.
        if ($nameIndex === false) {
            return;
        }

        foreach ($rows as $row) {
            $name = mb_substr($row[$nameIndex] ?? '', 0, 255);

            if ($name === '') {
                continue;
            }

            $product = $import->business->products()->withTrashed()->firstOrNew(['name' => $name]);

            if ($product->trashed()) {
                $product->restore();
            }

            foreach ($targets as $index => $target) {
                $value = $row[$index] ?? '';

                match (true) {
                    $value === '' => null,
                    $target === 'price' => $product->price = $this->toNumber($value),
                    $target === 'stock' => $product->stock = $this->toNumber($value),
                    $target === 'description' => $product->description = mb_substr($value, 0, 255),
                    default => null,
                };
            }

            $product->save();
        }
    }

    /**
     * One document per FILE, replaced on re-import: uploading a corrected
     * list must update the assistant's knowledge, never duplicate it. The
     * observer hashes the content and queues the chunking + embeddings.
     *
     * @param  list<list<string>>  $rows
     */
    private function feedKnowledge(ProductImport $import, array $rows): void
    {
        $columns = array_column($import->mapping, 'column');

        $content = collect($rows)
            ->map(fn (array $row): string => collect($columns)
                ->map(fn (string $column, int $index): string => ($row[$index] ?? '') === '' ? '' : $column.': '.$row[$index])
                ->filter()
                ->implode(' · '))
            ->filter()
            ->implode("\n");

        if ($content === '') {
            return;
        }

        KnowledgeDocument::query()->updateOrCreate(
            [
                'business_id' => $import->business_id,
                'source_type' => 'import',
                'title' => $import->original_name,
            ],
            ['content' => $content],
        );
    }

    /**
     * Prices arrive as people type them: "$ 1.234,56", "1,5", "3500". The
     * thousands dot only exists when a decimal comma follows.
     */
    private function toNumber(string $value): ?string
    {
        $clean = trim(str_replace(['$', ' '], '', $value));

        if (str_contains($clean, ',') && str_contains($clean, '.')) {
            $clean = str_replace('.', '', $clean);
        }

        $clean = str_replace(',', '.', $clean);

        return is_numeric($clean) ? $clean : null;
    }
}
