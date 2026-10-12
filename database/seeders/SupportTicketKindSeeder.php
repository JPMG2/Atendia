<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SupportTicketKind;
use Illuminate\Database\Seeder;

class SupportTicketKindSeeder extends Seeder
{
    /**
     * The three kinds the widget shipped with, keyed by `code`. She renames or
     * adds from the Catalogs hub, so this only creates what is missing and a
     * re-seed never gives back a name she changed.
     *
     * @var array<string, array{name: string, icon: string, starting_priority: string, runs_clock: bool, sort_order: int}>
     */
    public const array DEFAULTS = [
        'problem' => ['name' => 'Algo no funciona', 'icon' => 'alert-triangle', 'starting_priority' => 'high', 'runs_clock' => true, 'sort_order' => 10],
        'idea' => ['name' => 'Una idea', 'icon' => 'sparkles', 'starting_priority' => 'low', 'runs_clock' => false, 'sort_order' => 20],
        'question' => ['name' => 'Una pregunta', 'icon' => 'message-circle', 'starting_priority' => 'normal', 'runs_clock' => true, 'sort_order' => 30],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $code => $attributes) {
            SupportTicketKind::query()->firstOrCreate(['code' => $code], $attributes);
        }
    }
}
