<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PlatformSetting;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The way out for the owner. The code of the second step arrives by an
 * unofficial WhatsApp channel: if it goes down while the step is required,
 * nobody on the team can sign in to switch the requirement off from the
 * settings screen. The server is the one door that does not depend on it.
 */
#[Signature('atendia:staff-two-factor {days : Days the team has to turn the second step on; 0 stops asking}')]
#[Description('Sets the plazo of the second step for admin and support (0 = not required)')]
class SetStaffTwoFactorDays extends Command
{
    public function handle(): int
    {
        $days = (int) $this->argument('days');

        if ($days < 0 || $days > 60) {
            $this->error('The plazo goes from 0 to 60 days.');

            return self::FAILURE;
        }

        $setting = PlatformSetting::query()->where('key', 'security.staff_two_factor.grace_days')->first();

        if ($setting === null) {
            $this->error('The setting is not seeded: run db:seed --class=PlatformSettingSeeder.');

            return self::FAILURE;
        }

        // Through the model, not a bulk update: saving it is what drops the cached overrides.
        $setting->value = (string) $days;
        $setting->save();

        $this->info($days === 0 ? 'The second step is no longer required.' : "The team has {$days} days to turn the second step on.");

        return self::SUCCESS;
    }
}
