<?php

declare(strict_types=1);

namespace App\Classes\Report;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * What the person asked to export: what the screen shows (its filters travel in
 * the query string) or everything. A report reads its filters ONLY through here,
 * so "todo" can never leak a filter by accident.
 */
final class ReportScope
{
    /** True when at least one filter is active in what the screen shows. */
    public bool $isFiltered {
        get => ! $this->everything && array_filter($this->query, fn (string $value): bool => $value !== '') !== [];
    }

    /** @param  array<string, string>  $query */
    private function __construct(public readonly bool $everything, private readonly array $query) {}

    public static function fromRequest(?Request $request = null): self
    {
        return self::fromArray(($request ?? request())->query());
    }

    /** @param  array<string, mixed>  $query */
    public static function fromArray(array $query): self
    {
        $everything = ($query['alcance'] ?? '') === 'todo';
        unset($query['alcance']);

        return new self($everything, array_map(
            fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
            $query,
        ));
    }

    /** A picked day or range ("Y-m-d" or "Y-m-d..Y-m-d") as a person reads it: "01/10/2026 – 19/10/2026". */
    public function dateLabel(string $key): string
    {
        [$from, $to] = array_pad(explode('..', $this->value($key), 2), 2, null);
        $to ??= $from;

        if (! Carbon::hasFormat((string) $from, 'Y-m-d') || ! Carbon::hasFormat((string) $to, 'Y-m-d')) {
            return '';
        }

        $first = Carbon::createFromFormat('Y-m-d', (string) $from)->format('d/m/Y');
        $last = Carbon::createFromFormat('Y-m-d', (string) $to)->format('d/m/Y');

        return $first === $last ? $first : $first.' – '.$last;
    }

    /** One filter's value; empty when it is not set or when everything was asked. */
    public function value(string $key): string
    {
        return $this->everything ? '' : ($this->query[$key] ?? '');
    }
}
