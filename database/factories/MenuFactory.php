<?php

namespace Database\Factories;

use App\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'label_key' => 'menu.'.$this->faker->unique()->slug(2),
            'icon' => $this->faker->randomElement(['layout-dashboard', 'message-circle', 'calendar-check', 'package']),
            'route_name' => null,
            'badge' => null,
            'placement' => 'main',
            'sort_order' => $this->faker->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    /**
     * Make the item a child of the given parent.
     */
    public function childOf(Menu $parent): static
    {
        return $this->state(fn (): array => ['parent_id' => $parent->id]);
    }

    /**
     * Pin the item to the bottom navigation group.
     */
    public function bottom(): static
    {
        return $this->state(fn (): array => ['placement' => 'bottom']);
    }

    /**
     * Mark the item as inactive (hidden from the tree).
     */
    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
