<?php

namespace App\Jobs;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envia o evento "pedido pago" para o conector de WhatsApp (n8n + Evolution API),
 * que manda a mensagem para a Lara e a confirmação para a cliente.
 * O formato da mensagem fica no n8n: aqui vai só o dado estruturado.
 */
class NotifyOrderPaid implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public int $orderId) {}

    public function handle(): void
    {
        $order = Order::with('items')->find($this->orderId);

        if (! $order) {
            return;
        }

        if (! config('services.whatsapp.enabled') || blank(config('services.whatsapp.connector_url'))) {
            Log::info('Pedido pago (WhatsApp desativado)', ['order' => $order->code]);

            return;
        }

        Http::withToken((string) config('services.whatsapp.connector_token'))
            ->timeout(10)
            ->post(config('services.whatsapp.connector_url'), [
                'event' => 'order.paid',
                'store_phone' => config('shop.whatsapp'),
                'order' => [
                    'code' => $order->code,
                    'url_admin' => route('admin.orders.show', $order),
                    'url_customer' => route('orders.show', $order),
                    'customer_name' => $order->customer['name'] ?? null,
                    'customer_phone' => $order->customer['phone'] ?? null,
                    'delivery' => $order->delivery_method->label(),
                    'address' => $order->address,
                    'total' => Money::brl($order->total),
                    'needs_attention' => $order->needs_attention,
                    'attention_reason' => $order->attention_reason,
                    'summary' => $order->whatsappSummary(),
                ],
            ])
            ->throw();
    }
}
