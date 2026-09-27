<?php

declare(strict_types=1);

namespace App\Dto;

/** A report's content, format-agnostic: the same rows feed the PDF, the Excel and the CSV. */
final readonly class ReportDto
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<string|int|float|null>>  $rows
     */
    public function __construct(
        public string $title,
        public string $filename,
        public array $columns,
        public array $rows,
        public ?string $subtitle = null,
    ) {}
}
