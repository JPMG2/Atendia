<?php

declare(strict_types=1);

namespace App\Classes\Report;

use App\Dto\ReportDto;
use App\Enums\ReportAudience;
use App\Interfaces\Main\Report;

/**
 * A report over a list on a screen. The screen and the file read the SAME
 * filters through ReportScope, so what is exported is what was on the screen,
 * or everything when the person asked for it. A subclass says its columns and
 * how to cut its rows; the document is assembled here, once.
 */
abstract class ListReport implements Report
{
    abstract public string $title { get; }

    /** The file's base name, before the business and the date. */
    abstract public string $filename { get; }

    /** @var list<string> */
    abstract public array $columns { get; }

    abstract public ReportAudience $audience { get; }

    /** How many records exist with no filter at all. */
    abstract public int $total { get; }

    /**
     * @return list<list<string|int|float|null>>
     */
    abstract protected function rows(ReportScope $scope): array;

    /** How many rows the scope yields, without building them: the menu asks this on every render. */
    abstract protected function shown(ReportScope $scope): int;

    /**
     * What is filtering the list, as a person reads it: [['label' => 'Estado', 'value' => 'Con permiso']].
     *
     * @return list<array{label: string, value: string}>
     */
    abstract protected function filters(ReportScope $scope): array;

    public ReportDto $document {
        get {
            $scope = ReportScope::fromRequest();

            return new ReportDto(
                title: $this->title,
                filename: $this->filename,
                columns: $this->columns,
                rows: $this->rows($scope),
                filters: $this->filters($scope),
                total: $this->total,
                audience: $this->audience,
            );
        }
    }

    /**
     * What the export menu shows before anything is downloaded: the filters in
     * force and the counts, so the choice between "what you see" and "all" is
     * made knowing what each one holds.
     *
     * @param  array<string, mixed>  $query
     * @return array{filters: list<array{label: string, value: string}>, shown: int, total: int}
     */
    public function summary(array $query): array
    {
        $scope = ReportScope::fromArray($query);

        return ['filters' => $this->filters($scope), 'shown' => $this->shown($scope), 'total' => $this->total];
    }
}
