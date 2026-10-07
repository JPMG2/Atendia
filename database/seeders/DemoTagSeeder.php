<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DemoTag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Lang;

/**
 * The eight hero examples, moved out of `lang` and into the table she edits.
 *
 * `firstOrCreate` and not `updateOrCreate` on purpose, unlike its sibling
 * catalog seeders: these rows are content, and the whole point of the screen
 * is that she rewrites them. A re-run must never undo an afternoon of edits.
 */
class DemoTagSeeder extends Seeder
{
    public function run(): void
    {
        $order = 0;

        foreach ($this->rubros() as $slug => $rubro) {
            DemoTag::firstOrCreate(
                ['slug' => $slug, 'seasonal_window_id' => null],
                [
                    'label' => $rubro['label'] ?? $slug,
                    'business_name' => $rubro['name'] ?? '',
                    'noun' => $rubro['noun'] ?? '',
                    'chips' => $rubro['chips'] ?? [],
                    'pool' => $rubro['pool'] === [] ? $this->canonicalPool() : $rubro['pool'],
                    'sort_order' => $order += 10,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return array<string, array{label: string, name: string, noun: string, chips: list<string>, pool: list<array{side: string, text: string}>}>
     */
    private function rubros(): array
    {
        /** @var array<string, array<string, mixed>> $rubros */
        $rubros = Lang::get('landing.demo.rubros', [], 'es');

        return collect($rubros)
            ->map(fn (array $rubro): array => [...$rubro, 'pool' => $rubro['pool'] ?? []])
            ->all();
    }

    /**
     * The clinic carried no pool of its own: it replayed the canonical
     * `landing.phone.b5..b10` lines, which the view special-cased for it.
     *
     * @return list<array{side: string, text: string}>
     */
    private function canonicalPool(): array
    {
        $sides = ['in', 'out', 'in', 'out', 'in', 'out'];

        return collect(range(5, 10))
            ->map(fn (int $index, int $position): array => [
                'side' => $sides[$position],
                'text' => (string) Lang::get('landing.phone.b'.$index, [], 'es'),
            ])
            ->all();
    }
}
