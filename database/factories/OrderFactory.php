<?php

namespace Database\Factories;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => Order::generateCode(),
            'user_id' => User::factory(),
            'status' => OrderStatus::AwaitingPayment,
            'delivery_method' => DeliveryMethod::Pickup,
            'customer' => ['name' => fake()->name(), 'email' => fake()->safeEmail(), 'phone' => '69999990000', 'cpf' => '52998224725'],
            'subtotal' => 100,
            'shipping_fee' => 0,
            'total' => 100,
            'payment_method' => PaymentMethod::Pix,
            'asaas_payment_id' => 'pay_'.fake()->unique()->numerify('##########'),
        ];
    }

    public function status(OrderStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    /** Adiciona um item da variante informada e ajusta os totais. */
    public function withItem(ProductVariant $variant, int $quantity = 1): static
    {
        return $this->afterCreating(function (Order $order) use ($variant, $quantity) {
            $total = round((float) $variant->price * $quantity, 2);
            $order->items()->create([
                'product_variant_id' => $variant->id,
                'product_name' => $variant->product->name,
                'variant_label' => $variant->label,
                'sku' => $variant->sku,
                'unit_price' => $variant->price,
                'quantity' => $quantity,
                'total' => $total,
            ]);
            $subtotal = (float) $order->items()->sum('total');
            $order->update(['subtotal' => $subtotal, 'total' => $subtotal + (float) $order->shipping_fee]);
        });
    }
}
