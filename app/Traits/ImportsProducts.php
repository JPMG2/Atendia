<?php

declare(strict_types=1);

namespace App\Traits;

use App\Dto\NotificationDto;
use App\Enums\NotificationType;
use App\Jobs\ProcessProductImport;
use App\Models\ProductImport;
use App\Services\ProductImport\ColumnMapper;
use App\Services\ProductImport\ImportFileReader;
use App\Services\ProductImport\NameReviewer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * The spreadsheet import flow, written ONCE for every screen that accepts
 * one (wizard step, products screen): read the file, let the mapper propose
 * where each column lands, review the AI's typo fixes, confirm, and the
 * queued job does the heavy write. The host component renders its own
 * chrome, provides `validateImportUpload()` and may hook `importQueued()`.
 */
trait ImportsProducts
{
    /** @var TemporaryUploadedFile|null */
    public $upload = null;

    /** @var list<string> */
    public array $headers = [];

    /** @var list<string> One proposed target per column, index-aligned. */
    public array $mapping = [];

    /** @var list<string> The headers with typos fixed — editable suggestions. */
    public array $labels = [];

    /**
     * Names the AI flagged as data typos, index-aligned with $fixes. Locked:
     * the original is what the sheet says, only the fix is up for editing.
     *
     * @var list<string>
     */
    #[Locked]
    public array $fixOriginals = [];

    /** @var list<string> The proposed spellings — editable suggestions. */
    public array $fixes = [];

    /** Which column fed the typo review, to re-run it when "name" moves. */
    #[Locked]
    public ?int $nameIndex = null;

    public int $totalRows = 0;

    /** The first product of the sheet: a preview may ask for something REAL. */
    public ?string $sampleProduct = null;

    public ?string $queuedFile = null;

    /** The host delegates to its Form — validation never lives in a component. */
    abstract protected function validateImportUpload(mixed $file): void;

    /** Hook for the host to react once the import is queued. */
    protected function importQueued(ProductImport $import): void {}

    /** The label of each mapping destination, for the review selects. */
    #[Computed]
    public function targetOptions(): array
    {
        return __('wizard.products.targets');
    }

    /** A fresh file opens the review: read the shape, propose the mapping. */
    public function updatedUpload(): void
    {
        $this->validateImportUpload($this->upload);

        try {
            $summary = app(ImportFileReader::class)->read($this->upload->getRealPath());
        } catch (Throwable) {
            $summary = ['headers' => [], 'samples' => [], 'total_rows' => 0];
        }

        if ($summary['headers'] === []) {
            $this->reset('upload');

            $this->dispatchNotification(new NotificationDto(__('wizard.products.unreadable'), NotificationType::Error));

            return;
        }

        $proposal = app(ColumnMapper::class)->map($summary['headers'], $summary['samples']);

        $nameIndex = array_search('name', $proposal['targets'], true);

        $this->headers = $summary['headers'];
        $this->totalRows = $summary['total_rows'];
        $this->mapping = $proposal['targets'];
        $this->labels = $proposal['labels'];
        $sample = $nameIndex === false ? '' : trim($summary['samples'][0][$nameIndex] ?? '');
        $this->sampleProduct = $sample === '' ? null : $sample;
        $this->queuedFile = null;

        $this->proposeNameFixes();
    }

    /** Re-targeting the name column re-runs the typo review on the right one. */
    public function updatedMapping(): void
    {
        $index = array_search('name', $this->mapping, true);

        if (($index === false ? null : $index) !== $this->nameIndex) {
            $this->proposeNameFixes();
        }
    }

    /** Data typos, AI-suggested and person-confirmed — never a silent rewrite. */
    private function proposeNameFixes(): void
    {
        $index = array_search('name', $this->mapping, true);

        $this->nameIndex = $index === false ? null : $index;
        $this->fixOriginals = [];
        $this->fixes = [];

        if ($index === false || $this->upload === null) {
            return;
        }

        try {
            $names = app(ImportFileReader::class)->column($this->upload->getRealPath(), $index);
        } catch (Throwable) {
            return;
        }

        $corrections = app(NameReviewer::class)->review($names);

        $this->fixOriginals = array_keys($corrections);
        $this->fixes = array_values($corrections);
    }

    /** Stores the file and the confirmed mapping; the queued job takes over. */
    public function confirmImport(): void
    {
        $business = Auth::user()?->business;

        if ($business === null || $this->upload === null) {
            $this->dispatchNotification(new NotificationDto(__('notifications.not_found'), NotificationType::Error));

            return;
        }

        $original = $this->upload->getClientOriginalName();

        // Only real, confirmed changes travel: a fix edited back to the
        // original (or blanked) means "the sheet was right, leave it".
        $corrections = collect($this->fixOriginals)
            ->map(fn (string $name, int $index): array => [
                'original' => $name,
                'fixed' => trim($this->fixes[$index] ?? ''),
            ])
            ->filter(fn (array $fix): bool => $fix['fixed'] !== '' && $fix['fixed'] !== $fix['original'])
            ->values();

        $path = $this->upload->storeAs(
            'imports/business-'.$business->id,
            now()->format('YmdHis').'-'.Str::slug(pathinfo($original, PATHINFO_FILENAME)).'.'.strtolower($this->upload->getClientOriginalExtension()),
            'local',
        );

        $import = $business->productImports()->create([
            'original_name' => $original,
            'path' => $path,
            'mapping' => collect($this->headers)
                ->map(fn (string $header, int $index): array => [
                    'column' => $header,
                    'label' => trim($this->labels[$index] ?? '') !== '' ? trim($this->labels[$index]) : $header,
                    'target' => $this->mapping[$index] ?? 'extra',
                ])
                ->values()
                ->all(),
            'corrections' => $corrections->isEmpty() ? null : $corrections->all(),
            'total_rows' => $this->totalRows,
            'status' => 'pending',
        ]);

        ProcessProductImport::dispatch($import->id);

        $this->queuedFile = $original;

        // A preview must ask for the name as it will be written.
        $this->sampleProduct = $corrections->firstWhere('original', $this->sampleProduct)['fixed'] ?? $this->sampleProduct;

        $this->reset('upload', 'headers', 'mapping', 'labels', 'fixOriginals', 'fixes', 'nameIndex');

        $this->importQueued($import);
    }

    public function cancelUpload(): void
    {
        $this->reset('upload', 'headers', 'mapping', 'labels', 'fixOriginals', 'fixes', 'nameIndex');

        $this->totalRows = 0;
    }
}
