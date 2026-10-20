<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LogProblemState;
use App\Models\LogProblem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LogProblem>
 */
class LogProblemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'fingerprint' => sha1($title),
            'level' => 'error',
            'title' => $title,
            'exception' => null,
            'location' => null,
            'first_seen_at' => now()->subDays(3),
            'last_seen_at' => now()->subHour(),
            'last_file' => 'laravel.log',
            'state' => LogProblemState::Active,
        ];
    }

    public function resolved(): static
    {
        return $this->state(['state' => LogProblemState::Resolved, 'state_changed_at' => now()->subDay()]);
    }

    public function muted(): static
    {
        return $this->state(['state' => LogProblemState::Muted, 'state_changed_at' => now()->subDay()]);
    }

    public function warning(): static
    {
        return $this->state(['level' => 'warning']);
    }
}
