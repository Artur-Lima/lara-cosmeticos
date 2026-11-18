<?php

namespace App\Console\Commands;

use App\Actions\Orders\CancelOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Rede de segurança: o Asaas avisa (PAYMENT_OVERDUE) quando a cobrança vence,
 * mas se o webhook falhar o pedido não pode ficar "aguardando" para sempre.
 * Se o pagamento cair depois, ConfirmOrderPayment marca o pedido para atenção.
 */
class ExpirePendingOrders extends Command
{
    protected $signature = 'orders:expire-pending {--hours=48 : Idade mínima do pedido em horas}';

    protected $description = 'Cancela pedidos aguardando pagamento há mais tempo que o limite';

    public function handle(CancelOrder $cancel): int
    {
        $count = 0;

        Order::where('status', OrderStatus::AwaitingPayment)
            ->where('created_at', '<', now()->subHours((int) $this->option('hours')))
            ->each(function (Order $order) use ($cancel, &$count) {
                $cancel->handle($order, 'Cancelado automaticamente: pagamento não identificado no prazo.');
                $count++;
            });

        $this->info("{$count} pedido(s) cancelado(s).");

        return self::SUCCESS;
    }
}
