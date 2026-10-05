<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

/**
 * The knobs she may turn without a deploy.
 *
 * Only values the code REALLY reads: a row for something nothing honours is a
 * control that does nothing. `referral.reward_percent` is out for that reason
 * — it is printed to the client and no line of the app pays it.
 */
class PlatformSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // When the automatic sends go out, on EACH business's own clock.
            ['key' => 'schedule.birthday_greetings', 'group' => 'sends', 'type' => 'time', 'default_value' => '09:15', 'sort_order' => 1],
            ['key' => 'schedule.whatsapp_digest', 'group' => 'sends', 'type' => 'time', 'default_value' => '20:30', 'sort_order' => 2],
            ['key' => 'schedule.billing_cycle', 'group' => 'sends', 'type' => 'time', 'default_value' => '09:00', 'sort_order' => 3],
            ['key' => 'schedule.knowledge_digest.time', 'group' => 'sends', 'type' => 'time', 'default_value' => '09:30', 'sort_order' => 4],
            ['key' => 'schedule.knowledge_digest.weekday', 'group' => 'sends', 'type' => 'weekday', 'default_value' => '1', 'min' => 1, 'max' => 7, 'sort_order' => 5],
            ['key' => 'schedule.appointment_reminder_hours', 'group' => 'sends', 'type' => 'integer', 'default_value' => '24', 'min' => 1, 'max' => 72, 'sort_order' => 6],

            // When a conversation counts as over, which is when it is read.
            ['key' => 'analysis.idle_hours', 'group' => 'analysis', 'type' => 'integer', 'default_value' => '2', 'min' => 1, 'max' => 48, 'sort_order' => 1],

            // Handing a chat to a person, and getting it back.
            ['key' => 'handoff.reminder_minutes', 'group' => 'handoff', 'type' => 'integer', 'default_value' => '20', 'min' => 5, 'max' => 240, 'sort_order' => 1],
            ['key' => 'handoff.customer_idle_hours', 'group' => 'handoff', 'type' => 'integer', 'default_value' => '24', 'min' => 1, 'max' => 168, 'sort_order' => 2],

            // Money: when the owner is warned, and how long the assistant
            // keeps answering after the date went by.
            ['key' => 'billing.grace_days', 'group' => 'billing', 'type' => 'integer', 'default_value' => '5', 'min' => 0, 'max' => 30, 'sort_order' => 1],

            // What an invited business gets, which the trial really honours.
            ['key' => 'referral.invited_trial_days', 'group' => 'referral', 'type' => 'integer', 'default_value' => '21', 'min' => 0, 'max' => 90, 'sort_order' => 1],
            ['key' => 'referral.founders', 'group' => 'referral', 'type' => 'integer', 'default_value' => '10', 'min' => 0, 'max' => 500, 'sort_order' => 2],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'group' => $setting['group'],
                    'type' => $setting['type'],
                    // Seeding never overwrites what she chose: only the default
                    // and the bounds are the code's to keep up to date.
                    'value' => PlatformSetting::query()->where('key', $setting['key'])->value('value') ?? $setting['default_value'],
                    'default_value' => $setting['default_value'],
                    'min_value' => $setting['min'] ?? null,
                    'max_value' => $setting['max'] ?? null,
                    'sort_order' => $setting['sort_order'],
                ],
            );
        }
    }
}
