<?php

namespace App\Jobs;

use App\Actions\Orders\CancelOrder;
use App\Actions\Orders\ConfirmOrderPayment;
use App\Enums\OrderStatus;
use App\Models\AsaasWebhookEvent;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAsaasEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 300];

    public function __construct(public int $eventRecordId) {}

    public function handle(ConfirmOrderPayment $confirm, CancelOrder $cancel): void
    {
        $record = AsaasWebhookEvent::find($this->eventRecordId);

        if (! $record || $record->processed_at) {
            return;
        }

        $order = $record->payment_id ? Order::where('asaas_payment_id', $record->payment_id)->first() : null;

        $result = match (true) {
            ! $order => 'ignorado: cobrança não pertence a nenhum pedido',
            in_array($record->event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true) => $confirm->handle($order),
            in_array($record->event, ['PAYMENT_OVERDUE', 'PAYMENT_DELETED'], true) => $this->notPaid($order, $record->event, $cancel),
            $record->event === 'PAYMENT_REFUNDED' => $this->refunded($order, $cancel),
            default => 'ignorado: evento não tratado',
        };

        $record->update(['processed_at' => now(), 'result' => $result]);
    }

    private function notPaid(Order $order, string $event, CancelOrder $cancel): string
    {
        if ($order->status !== OrderStatus::AwaitingPayment) {
            return 'ignorado: pedido não está aguardando pagamento';
        }

        $cancel->handle($order, "Pagamento não realizado ({$event}).");

        return 'cancelado: pagamento não realizado';
    }

    private function refunded(Order $order, CancelOrder $cancel): string
    {
        if ($order->status === OrderStatus::Cancelled) {
            return 'ignorado: já cancelado';
        }

        // Ex.: PIX de conta PF confirmado e depois estornado na análise preventiva.
        $cancel->handle($order, 'Pagamento estornado pelo Asaas.');
        $order->flagAttention('Pagamento estornado pelo Asaas: confira se o pedido já tinha sido separado ou entregue.');

        return 'cancelado: estorno';
    }
}
