<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\SocialNetwork;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialNetwork>
 */
class SocialNetworkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            // `name` is UNIQUE in the table, so it has to be unique here too or a
            // test creating two networks blows up on the constraint.
            'name' => $name,
            'url' => 'https://www.'.$this->faker->unique()->domainWord().'.com/',
            // The icon is a key of config/icons.php; anything else renders nothing.
            'icon' => $this->faker->randomElement(array_keys(config('icons'))),
            'abbreviation' => mb_strtoupper(mb_substr($name, 0, 2)),
            'is_active' => true,
        ];
    }
}
