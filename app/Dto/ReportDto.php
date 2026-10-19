<?php

declare(strict_types=1);

namespace App\Dto;

use App\Enums\ReportAudience;

/** A report's content, format-agnostic: the same rows feed the PDF, the Excel and the CSV. */
final class ReportDto
{
    /** Whether the file shows a cut of the list: the name says so and the document lists the filters. */
    public bool $isFiltered {
        get => $this->filters !== [];
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<array{label: string, value: string}>  $filters  What was active when the file was made.
     * @param  ?int  $total  How many records exist without any filter, for "3 de 148".
     */
    public function __construct(
        public readonly string $title,
        public readonly string $filename,
        public readonly array $columns,
        public readonly array $rows,
        public readonly ?string $subtitle = null,
        public readonly array $filters = [],
        public readonly ?int $total = null,
        public readonly ReportAudience $audience = ReportAudience::Platform,
    ) {}
}
