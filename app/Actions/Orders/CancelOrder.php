<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancela o pedido e devolve ao estoque o que foi baixado na confirmação.
 * Estorno do dinheiro é feito pela loja no painel do Asaas (não automático).
 */
class CancelOrder
{
    public function handle(Order $order, string $note, ?User $by = null): void
    {
        DB::transaction(function () use ($order, $note, $by) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($locked->status === OrderStatus::Cancelled) {
                return;
            }

            // Devolve só o que de fato saiu do estoque (item em falta não foi baixado).
            $locked->items()->where('stock_deducted', true)->with('variant')->get()
                ->each(function (OrderItem $item) {
                    $item->variant?->incrementStock($item->quantity);
                    $item->update(['stock_deducted' => false]);
                });

            $locked->transitionTo(OrderStatus::Cancelled, $note, $by);

            $order->setRawAttributes($locked->getAttributes(), true);
        });
    }
}
