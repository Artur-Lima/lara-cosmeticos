<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Jobs\NotifyOrderPaid;
use App\Models\AsaasWebhookEvent;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AsaasWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste-com-mais-de-32-caracteres-123';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.asaas.key' => '$aact_hmlg_teste',
            'services.asaas.webhook_token' => self::TOKEN,
        ]);
        Bus::fake([NotifyOrderPaid::class]);
    }

    private function asaasSays(string $paymentId, string $status, float $value): void
    {
        Http::fake(["https://api-sandbox.asaas.com/v3/payments/{$paymentId}" => Http::response(['id' => $paymentId, 'status' => $status, 'value' => $value])]);
    }

    private function send(string $event, string $paymentId, ?string $eventId = null, ?string $token = self::TOKEN)
    {
        return $this->postJson('/api/asaas/webhook', [
            'id' => $eventId ?? 'evt_'.uniqid(),
            'event' => $event,
            'payment' => ['id' => $paymentId, 'object' => 'payment'],
        ], $token === null ? [] : ['asaas-access-token' => $token]);
    }

    private function orderWith(ProductVariant $variant, int $qty = 1, array $state = []): Order
    {
        return Order::factory()->withItem($variant, $qty)->create($state);
    }

    public function test_token_invalido_ausente_ou_nao_configurado_e_recusado(): void
    {
        $this->send('PAYMENT_RECEIVED', 'pay_1', token: 'errado')->assertUnauthorized();
        $this->send('PAYMENT_RECEIVED', 'pay_1', token: null)->assertUnauthorized();

        config(['services.asaas.webhook_token' => '']);
        $this->send('PAYMENT_RECEIVED', 'pay_1', token: '')->assertUnauthorized();

        $this->assertDatabaseCount('asaas_webhook_events', 0);
    }

    public function test_pagamento_confirmado_marca_pago_baixa_estoque_e_avisa(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 5]);
        $order = $this->orderWith($variant, 2);
        $this->asaasSays($order->asaas_payment_id, 'RECEIVED', 100);

        $this->send('PAYMENT_RECEIVED', $order->asaas_payment_id)->assertOk();

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertFalse($order->needs_attention);
        $this->assertTrue($order->items->sole()->stock_deducted);
        $this->assertSame(3, $variant->fresh()->stock);
        $this->assertSame('pago', AsaasWebhookEvent::sole()->result);
        Bus::assertDispatched(NotifyOrderPaid::class, fn ($job) => $job->orderId === $order->id);

        // Confere na API do Asaas em vez de confiar só no webhook.
        Http::assertSent(fn (Request $r) => $r->method() === 'GET' && str_ends_with($r->url(), $order->asaas_payment_id));
    }

    public function test_reentrega_do_mesmo_evento_e_ignorada(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 5]);
        $order = $this->orderWith($variant);
        $this->asaasSays($order->asaas_payment_id, 'RECEIVED', 50);

        $this->send('PAYMENT_RECEIVED', $order->asaas_payment_id, 'evt_igual')->assertOk();
        $this->send('PAYMENT_RECEIVED', $order->asaas_payment_id, 'evt_igual')->assertOk()->assertJson(['duplicate' => true]);

        $this->assertSame(4, $variant->fresh()->stock);
        $this->assertDatabaseCount('asaas_webhook_events', 1);
    }

    public function test_confirmed_e_received_do_mesmo_pagamento_baixam_estoque_uma_vez(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 5]);
        $order = $this->orderWith($variant);
        $this->asaasSays($order->asaas_payment_id, 'RECEIVED', 50);

        $this->send('PAYMENT_CONFIRMED', $order->asaas_payment_id);
        $this->send('PAYMENT_RECEIVED', $order->asaas_payment_id);

        $this->assertSame(4, $variant->fresh()->stock);
        $this->assertSame(1, $order->history()->where('to_status', 'pago')->count());
        Bus::assertDispatchedTimes(NotifyOrderPaid::class, 1);
    }

    public function test_nao_marca_pago_se_a_api_do_asaas_nao_confirma(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 5]);
        $order = $this->orderWith($variant);
        $this->asaasSays($order->asaas_payment_id, 'PENDING', 50);

        $this->send('PAYMENT_RECEIVED', $order->asaas_payment_id)->assertOk();

        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_valor_pago_menor_que_o_total_gera_alerta(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 50]);
        $order = $this->orderWith($variant);
        $this->asaasSays($order->asaas_payment_id, 'RECEIVED', 10);

        $this->send('PAYMENT_RECEIVED', $order->asaas_payment_id);

        $this->assertSame(OrderStatus::AwaitingPayment, $order->fresh()->status);
        $this->assertTrue($order->fresh()->needs_attention);
    }

    public function test_falta_de_estoque_no_pagamento_paga_e_marca_atencao(): void
    {
        $tem = ProductVariant::factory()->create(['price' => 50, 'stock' => 5]);
        $acabou = ProductVariant::factory()->create(['price' => 50, 'stock' => 0]);
        $order = Order::factory()->withItem($tem)->withItem($acabou)->create();
        $this->asaasSays($order->asaas_payment_id, 'RECEIVED', 100);

        $this->send('PAYMENT_RECEIVED', $order->asaas_payment_id);

        $order->refresh();
        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertTrue($order->needs_attention);
        $this->assertStringContainsString('Sem estoque', $order->attention_reason);
        $this->assertSame(4, $tem->fresh()->stock);
        $this->assertSame(0, $acabou->fresh()->stock);

        // Estorno depois: devolve só o que realmente saiu do estoque.
        $this->send('PAYMENT_REFUNDED', $order->asaas_payment_id);
        $this->assertSame(5, $tem->fresh()->stock);
        $this->assertSame(0, $acabou->fresh()->stock);
    }

    public function test_vencido_cancela_e_pagamento_tardio_gera_alerta(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 5]);
        $order = $this->orderWith($variant);

        $this->send('PAYMENT_OVERDUE', $order->asaas_payment_id);
        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);

        $this->asaasSays($order->asaas_payment_id, 'RECEIVED', 50);
        $this->send('PAYMENT_RECEIVED', $order->asaas_payment_id);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertTrue($order->needs_attention);
        $this->assertStringContainsString('depois do cancelamento', $order->attention_reason);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_estorno_apos_pago_cancela_devolve_estoque_e_alerta(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 5]);
        $order = $this->orderWith($variant, 2);
        $this->asaasSays($order->asaas_payment_id, 'CONFIRMED', 100);
        $this->send('PAYMENT_CONFIRMED', $order->asaas_payment_id);
        $this->assertSame(3, $variant->fresh()->stock);

        $this->send('PAYMENT_REFUNDED', $order->asaas_payment_id);

        $order->refresh();
        $this->assertSame(OrderStatus::Cancelled, $order->status);
        $this->assertTrue($order->needs_attention);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_evento_de_cobranca_desconhecida_e_ignorado_sem_erro(): void
    {
        $this->send('PAYMENT_RECEIVED', 'pay_que_nao_existe')->assertOk();

        $this->assertStringStartsWith('ignorado', AsaasWebhookEvent::sole()->result);
    }

    public function test_notificacao_envia_evento_para_o_conector_do_whatsapp(): void
    {
        Http::fake(['https://n8n.exemplo/*' => Http::response(['ok' => true])]);
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.connector_url' => 'https://n8n.exemplo/webhook/loja',
            'services.whatsapp.connector_token' => 'segredo',
            'shop.whatsapp' => '5569999990000',
        ]);
        $variant = ProductVariant::factory()->create(['price' => 50]);
        $order = $this->orderWith($variant, 2, ['status' => OrderStatus::Paid]);

        (new NotifyOrderPaid($order->id))->handle();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://n8n.exemplo/webhook/loja'
            && $r->hasHeader('Authorization', 'Bearer segredo')
            && $r['event'] === 'order.paid'
            && $r['order']['code'] === $order->code
            && str_contains($r['order']['summary'], '2x'));
    }

    public function test_expira_pedidos_antigos_aguardando_pagamento(): void
    {
        $velho = Order::factory()->create(['created_at' => now()->subHours(50)]);
        $novo = Order::factory()->create(['created_at' => now()->subHour()]);
        $pago = Order::factory()->status(OrderStatus::Paid)->create(['created_at' => now()->subDays(5)]);

        $this->artisan('orders:expire-pending')->assertSuccessful();

        $this->assertSame(OrderStatus::Cancelled, $velho->fresh()->status);
        $this->assertSame(OrderStatus::AwaitingPayment, $novo->fresh()->status);
        $this->assertSame(OrderStatus::Paid, $pago->fresh()->status);
    }
}
