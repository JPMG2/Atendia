<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * The three real packages. Keyed by code, so a re-run updates the
     * figures in place — and every screen follows on its own.
     */
    public function run(): void
    {
        $plans = [
            ['code' => 'emprende', 'price' => 29, 'conversations_per_month' => 300, 'team_seats' => 1, 'messages_per_hour' => 30, 'audio_minutes_per_month' => 0, 'statistics' => 'counts', 'ask_per_month' => 0, 'catalog_photos' => 100, 'photos_per_item' => 3, 'reads_media' => false, 'departments' => false, 'trial_days' => null, 'is_featured' => false],
            ['code' => 'negocio', 'price' => 79, 'conversations_per_month' => 1000, 'team_seats' => 2, 'messages_per_hour' => 60, 'audio_minutes_per_month' => 200, 'statistics' => 'patterns', 'ask_per_month' => 100, 'catalog_photos' => 1000, 'photos_per_item' => 5, 'reads_media' => true, 'departments' => true, 'trial_days' => 14, 'is_featured' => true],
            ['code' => 'premium', 'price' => 149, 'conversations_per_month' => 3000, 'team_seats' => 4, 'messages_per_hour' => 120, 'audio_minutes_per_month' => 600, 'statistics' => 'trends', 'ask_per_month' => 500, 'catalog_photos' => 5000, 'photos_per_item' => 10, 'reads_media' => true, 'departments' => true, 'trial_days' => null, 'is_featured' => false],
        ];

        foreach ($plans as $order => $plan) {
            SubscriptionPlan::query()->updateOrCreate(['code' => $plan['code']], [...$plan, 'sort_order' => $order + 1]);
        }
    }
}
