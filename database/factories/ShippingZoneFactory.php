<?php

namespace Database\Factories;

use App\Models\ShippingZone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingZone>
 */
class ShippingZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Bairro '.fake()->unique()->lastName(),
            'fee' => 5.00,
            'free_above' => null,
            'estimate' => 'no mesmo dia',
            'is_active' => true,
            'position' => 0,
        ];
    }
}
