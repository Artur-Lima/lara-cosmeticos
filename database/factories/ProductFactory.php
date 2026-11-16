<?php

namespace Database\Factories;

use App\Enums\ProductGender;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'brand_id' => Brand::factory(),
            'category_id' => Category::factory(),
            'name' => ucfirst(fake()->unique()->words(3, true)),
            'short_description' => fake()->sentence(10),
            'description' => fake()->paragraphs(2, true),
            'gender' => fake()->randomElement(ProductGender::cases()),
            'status' => ProductStatus::Active,
            'is_featured' => false,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::Draft]);
    }

    public function featured(): static
    {
        return $this->state(['is_featured' => true]);
    }

    /** Perfume com variações de volume (ex.: 30/75/100 ml), a do meio como padrão. */
    public function withVolumes(array $volumes = [30, 75, 100]): static
    {
        return $this->afterCreating(function (Product $product) use ($volumes) {
            $middle = intdiv(count($volumes), 2);

            foreach (array_values($volumes) as $i => $ml) {
                ProductVariant::factory()->for($product)->create([
                    'label' => "{$ml} ml",
                    'volume_ml' => $ml,
                    'price' => round(40 + $ml * 1.2, 2),
                    'is_default' => $i === $middle,
                    'position' => $i,
                ]);
            }
        });
    }
}
