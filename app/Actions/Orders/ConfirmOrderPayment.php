<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Jobs\NotifyOrderPaid;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Asaas\AsaasClient;
use Illuminate\Support\Facades\DB;

/**
 * Marca o pedido como pago e baixa o estoque (ADR-005).
 * - Confere a cobrança direto na API do Asaas: não confia só no corpo do webhook.
 * - Idempotente: CONFIRMED e RECEIVED do mesmo pagamento chegam juntos; só um efetiva.
 * - Item sem estoque suficiente não bloqueia o pedido: ele é pago e marcado
 *   "precisa de atenção" para a loja resolver (troca ou estorno).
 */
class ConfirmOrderPayment
{
    private const PAID_STATUSES = ['CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH'];

    public function __construct(private readonly AsaasClient $asaas) {}

    public function handle(Order $order): string
    {
        if ($order->status->isPaid()) {
            return 'ignorado: já pago';
        }

        // Chamada HTTP fora da transação: não segura lock do banco esperando rede.
        $payment = $this->asaas->getPayment($order->asaas_payment_id);

        if (! in_array($payment['status'] ?? null, self::PAID_STATUSES, true)) {
            return 'ignorado: Asaas informa status '.($payment['status'] ?? 'desconhecido');
        }

        if (round((float) ($payment['value'] ?? 0), 2) < round((float) $order->total, 2)) {
            $order->flagAttention('Valor pago no Asaas ('.$payment['value'].') menor que o total do pedido.');

            return 'atenção: valor divergente';
        }

        $result = DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();

            if ($order->status->isPaid()) {
                return 'ignorado: já pago';
            }

            if ($order->status === OrderStatus::Cancelled) {
                $order->flagAttention('Pagamento recebido depois do cancelamento: reative o pedido ou estorne no Asaas.');

                return 'atenção: pago após cancelamento';
            }

            $missing = $order->items()->with('variant')->get()
                ->reject(function (OrderItem $item) {
                    $deducted = $item->variant?->decrementStock($item->quantity) === true;
                    $item->update(['stock_deducted' => $deducted]);

                    return $deducted;
                })
                ->map(fn (OrderItem $item) => "{$item->quantity}x {$item->product_name} ({$item->variant_label})");

            $order->transitionTo(OrderStatus::Paid, 'Pagamento confirmado pelo Asaas.');

            if ($missing->isNotEmpty()) {
                $order->flagAttention('Sem estoque no momento do pagamento: '.$missing->implode(', ').'.');
            }

            return 'pago'.($missing->isNotEmpty() ? ' (com falta de estoque)' : '');
        });

        if (str_starts_with($result, 'pago')) {
            NotifyOrderPaid::dispatch($order->id)->afterCommit();
        }

        return $result;
    }
}
