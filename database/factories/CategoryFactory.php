<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'position' => 0,
            'is_active' => true,
        ];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(['parent_id' => $parent->id]);
    }
}
