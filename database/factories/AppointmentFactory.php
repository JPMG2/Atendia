<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->addDay()->setTime(10, 0);

        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
            'source' => AppointmentSource::Assistant,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['status' => AppointmentStatus::Cancelled]);
    }
}
