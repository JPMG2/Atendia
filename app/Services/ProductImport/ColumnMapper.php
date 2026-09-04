<?php

declare(strict_types=1);

namespace App\Services\ProductImport;

use App\Ai\Agents\ProductColumnMapper;
use Illuminate\Support\Str;
use Throwable;

/**
 * Decides where each spreadsheet column lands: deterministic synonyms first,
 * the AI agent only for what they cannot place, and `extra` as the floor —
 * an unknown column is data for the assistant, never an error. The caller
 * always gets a full mapping, whatever happens to the AI.
 */
class ColumnMapper
{
    /**
     * Header synonyms per core field, compared unaccented and lowercased.
     * The order is the priority: the first column matching a field takes it.
     */
    private const array SYNONYMS = [
        'name' => ['nombre', 'producto', 'productos', 'articulo', 'articulos', 'item', 'items', 'nombre del producto', 'product', 'title'],
        'price' => ['precio', 'precio unitario', 'importe', 'valor', 'monto', 'pvp', 'price', '$'],
        'stock' => ['stock', 'cantidad', 'cant', 'cant.', 'unidades', 'existencia', 'existencias', 'disponible', 'disponibles', 'qty', 'quantity'],
        'description' => ['descripcion', 'detalle', 'detalles', 'observaciones', 'notas', 'description'],
    ];

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $samples
     * @return list<string> One target per column, index-aligned with headers.
     */
    public function map(array $headers, array $samples): array
    {
        $targets = array_fill(0, count($headers), null);
        $taken = [];

        foreach ($headers as $index => $header) {
            $field = $this->fieldForHeader($header);

            if ($field !== null && ! isset($taken[$field])) {
                $targets[$index] = $field;
                $taken[$field] = true;
            }
        }

        $targets = $this->askAgentForUnresolved($headers, $samples, $targets, $taken);

        // The floor of the whole feature: a column nobody could place is
        // still data — it becomes knowledge, so it must never block.
        $targets = array_map(fn (?string $target): string => $target ?? 'extra', $targets);

        if (! in_array('name', $targets, true) && $targets !== []) {
            $targets[0] = 'name';
        }

        return $targets;
    }

    private function fieldForHeader(string $header): ?string
    {
        $normalized = trim(mb_strtolower(Str::ascii($header)));

        foreach (self::SYNONYMS as $field => $synonyms) {
            if (in_array($normalized, $synonyms, true)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $samples
     * @param  list<string|null>  $targets
     * @param  array<string, bool>  $taken
     * @return list<string|null>
     */
    private function askAgentForUnresolved(array $headers, array $samples, array $targets, array $taken): array
    {
        $unresolved = array_keys(array_filter($targets, fn (?string $target): bool => $target === null));

        if ($unresolved === []) {
            return $targets;
        }

        try {
            $columns = collect($unresolved)->map(fn (int $index): array => [
                'column' => $headers[$index],
                'samples' => array_column($samples, $index),
            ]);

            $response = new ProductColumnMapper()->prompt(
                'Classify these spreadsheet columns: '.$columns->toJson(JSON_UNESCAPED_UNICODE),
            );

            $byColumn = collect($response['mappings'] ?? [])->pluck('target', 'column');

            foreach ($unresolved as $index) {
                $target = $byColumn->get($headers[$index]);

                // A core field already taken cannot be granted twice; the
                // agent's answer degrades to extra instead of clashing.
                if (! in_array($target, ProductColumnMapper::TARGETS, true) || isset($taken[$target])) {
                    continue;
                }

                $targets[$index] = $target;

                if ($target !== 'extra') {
                    $taken[$target] = true;
                }
            }
        } catch (Throwable) {
            // The AI being down must never break an upload: everything the
            // heuristics missed simply lands on extra.
        }

        return $targets;
    }
}
