<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        $ml = fake()->randomElement([30, 50, 75, 100]);

        return [
            'product_id' => Product::factory(),
            'sku' => 'LL-'.fake()->unique()->bothify('????-####'),
            'label' => "{$ml} ml",
            'volume_ml' => $ml,
            'price' => fake()->randomFloat(2, 29, 299),
            'stock' => fake()->numberBetween(1, 20),
            'is_default' => false,
            'is_active' => true,
            'position' => 0,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(['stock' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function onSale(float $from, float $to): static
    {
        return $this->state(['compare_at_price' => $from, 'price' => $to]);
    }

    /** Variante de cor (maquiagem), sem volume. */
    public function shade(string $name, string $hex): static
    {
        return $this->state([
            'label' => $name,
            'shade' => $name,
            'color_hex' => $hex,
            'volume_ml' => null,
        ]);
    }
}
