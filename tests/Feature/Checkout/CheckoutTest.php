<?php

namespace Tests\Feature\Checkout;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const ASAAS = 'https://api-sandbox.asaas.com/v3';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.key' => '$aact_hmlg_teste', 'services.asaas.env' => 'sandbox']);
    }

    private function fakeAsaas(array $paymentOverrides = []): void
    {
        Http::fake([
            self::ASAAS.'/customers' => Http::response(['id' => 'cus_123']),
            self::ASAAS.'/payments' => Http::response($paymentOverrides + ['id' => 'pay_999', 'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_999']),
            self::ASAAS.'/payments/pay_999/pixQrCode' => Http::response([
                'encodedImage' => base64_encode('png'), 'payload' => '00020126PIXCOPIAECOLA', 'expirationDate' => '2026-11-19 23:59:59',
            ]),
        ]);
    }

    private function buyer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['cpf' => '52998224725', 'phone' => '69999991234']);
    }

    private function cartWith(ProductVariant $variant, int $qty = 1): void
    {
        $this->post('/sacola', ['variant_id' => $variant->id, 'quantity' => $qty]);
    }

    private function pickupPix(array $overrides = []): array
    {
        return $overrides + ['delivery_method' => 'retirada', 'payment_method' => 'pix', 'expected_subtotal' => '100.00'];
    }

    public function test_visitante_vai_para_o_login_e_volta_ao_checkout(): void
    {
        $this->get('/finalizar')->assertRedirect('/login');

        $user = $this->buyer();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/finalizar');
    }

    public function test_sacola_vazia_volta_para_a_sacola(): void
    {
        $this->actingAs($this->buyer())->get('/finalizar')->assertRedirect('/sacola');
    }

    public function test_pedido_pix_com_retirada(): void
    {
        $this->fakeAsaas();
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 5]);
        $user = $this->buyer();
        $this->actingAs($user);
        $this->cartWith($variant, 2);

        $response = $this->post('/finalizar', $this->pickupPix(['notes' => 'É presente']));

        $order = Order::sole();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::AwaitingPayment, $order->status);
        $this->assertSame('100.00', $order->total);
        $this->assertSame('0.00', $order->shipping_fee);
        $this->assertNull($order->address);
        $this->assertSame('pay_999', $order->asaas_payment_id);
        $this->assertSame('00020126PIXCOPIAECOLA', $order->pix_payload);
        $this->assertSame('É presente', $order->notes);
        $this->assertSame(2, $order->items->sole()->quantity);
        $this->assertSame($variant->sku, $order->items->sole()->sku);

        $this->assertSame(5, $variant->fresh()->stock, 'Estoque só baixa na confirmação do pagamento (ADR-005).');
        $this->assertSame([], session('cart.items'));
        $this->assertSame('cus_123', $user->fresh()->asaas_customer_id);

        Http::assertSent(fn (Request $r) => $r->url() === self::ASAAS.'/payments'
            && $r['billingType'] === 'PIX'
            && $r['value'] === 100.0
            && $r['externalReference'] === $order->code
            && $r->hasHeader('access_token', '$aact_hmlg_teste')
            && str_contains($r->header('User-Agent')[0], 'LaraLimaCosmeticos'));

        $this->get(route('orders.show', $order))->assertOk()->assertSee('Falta só o PIX')->assertSee('00020126PIXCOPIAECOLA');
    }

    public function test_entrega_cobra_taxa_do_bairro_e_frete_gratis_acima_do_valor(): void
    {
        $this->fakeAsaas();
        $centro = ShippingZone::factory()->create(['name' => 'Centro', 'fee' => 8, 'free_above' => 150]);
        $variant = ProductVariant::factory()->create(['price' => 100, 'stock' => 5]);
        $this->actingAs($this->buyer());

        $this->cartWith($variant);
        $this->post('/finalizar', [
            'delivery_method' => 'entrega', 'payment_method' => 'pix', 'expected_subtotal' => '100.00',
            'shipping_zone_id' => $centro->id, 'street' => 'Rua das Flores', 'number' => '12', 'reference' => 'Casa azul',
        ])->assertSessionHasNoErrors();

        $order = Order::sole();
        $this->assertSame('8.00', $order->shipping_fee);
        $this->assertSame('108.00', $order->total);
        $this->assertSame('Centro', $order->address['neighborhood']);
        $this->assertSame('Itapuã do Oeste', $order->address['city']);

        // Segundo pedido acima de R$ 150: frete grátis.
        $this->cartWith($variant, 2);
        $this->post('/finalizar', [
            'delivery_method' => 'entrega', 'payment_method' => 'pix', 'expected_subtotal' => '200.00',
            'shipping_zone_id' => $centro->id, 'street' => 'Rua das Flores', 'number' => '12',
        ]);
        $this->assertSame('0.00', Order::latest('id')->first()->shipping_fee);
    }

    public function test_entrega_exige_bairro_ativo_e_endereco(): void
    {
        $inativo = ShippingZone::factory()->create(['is_active' => false]);
        $this->actingAs($this->buyer());
        $this->cartWith(ProductVariant::factory()->create(['price' => 100]));

        $this->post('/finalizar', ['delivery_method' => 'entrega', 'payment_method' => 'pix', 'expected_subtotal' => '100.00', 'shipping_zone_id' => $inativo->id])
            ->assertSessionHasErrors(['shipping_zone_id', 'street', 'number']);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_cpf_exigido_validado_e_salvo_na_primeira_compra(): void
    {
        $this->fakeAsaas();
        $user = User::factory()->create(['cpf' => null]);
        $this->actingAs($user);
        $this->cartWith(ProductVariant::factory()->create(['price' => 100]));

        $this->get('/finalizar')->assertSee('Necessário para emitir a cobrança');
        $this->post('/finalizar', $this->pickupPix())->assertSessionHasErrors('cpf');
        $this->post('/finalizar', $this->pickupPix(['cpf' => '111.111.111-11']))->assertSessionHasErrors('cpf');

        $this->post('/finalizar', $this->pickupPix(['cpf' => '529.982.247-25']))->assertSessionHasNoErrors();
        $this->assertSame('52998224725', $user->fresh()->cpf);
    }

    public function test_cpf_de_outra_conta_e_recusado(): void
    {
        User::factory()->create(['cpf' => '52998224725']);
        $this->actingAs(User::factory()->create(['cpf' => null]));
        $this->cartWith(ProductVariant::factory()->create(['price' => 100]));

        $this->post('/finalizar', $this->pickupPix(['cpf' => '52998224725']))->assertSessionHasErrors('cpf');
    }

    public function test_cartao_redireciona_para_pagina_segura_do_asaas(): void
    {
        $this->fakeAsaas();
        $this->actingAs($this->buyer());
        $this->cartWith(ProductVariant::factory()->create(['price' => 100]));

        $this->post('/finalizar', $this->pickupPix(['payment_method' => 'cartao']))
            ->assertRedirect('https://sandbox.asaas.com/i/pay_999');

        Http::assertSent(fn (Request $r) => $r->url() === self::ASAAS.'/payments' && $r['billingType'] === 'CREDIT_CARD');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'pixQrCode'));
    }

    public function test_reaproveita_cliente_asaas_existente(): void
    {
        $this->fakeAsaas();
        $this->actingAs($this->buyer(['asaas_customer_id' => null]));
        auth()->user()->forceFill(['asaas_customer_id' => 'cus_existente'])->save();
        $this->cartWith(ProductVariant::factory()->create(['price' => 100]));

        $this->post('/finalizar', $this->pickupPix());

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/customers'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/payments') && $r['customer'] === 'cus_existente');
    }

    public function test_falha_do_asaas_cancela_pedido_e_mantem_a_sacola(): void
    {
        Http::fake([
            self::ASAAS.'/customers' => Http::response(['id' => 'cus_123']),
            self::ASAAS.'/payments' => Http::response(['errors' => [['description' => 'Chave PIX não cadastrada']]], 400),
        ]);
        $variant = ProductVariant::factory()->create(['price' => 100]);
        $this->actingAs($this->buyer());
        $this->cartWith($variant);

        $this->post('/finalizar', $this->pickupPix())
            ->assertRedirect('/finalizar')
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Sua sacola continua salva'));

        $this->assertSame(OrderStatus::Cancelled, Order::sole()->status);
        $this->assertSame([$variant->id => 1], session('cart.items'));
    }

    public function test_preco_mudou_durante_o_checkout_nao_cobra(): void
    {
        $this->fakeAsaas();
        $variant = ProductVariant::factory()->create(['price' => 100]);
        $this->actingAs($this->buyer());
        $this->cartWith($variant);

        $variant->update(['price' => 120]); // Lara mudou o preço enquanto o cliente preenchia

        $this->post('/finalizar', $this->pickupPix(['expected_subtotal' => '100.00']))
            ->assertRedirect('/finalizar')
            ->assertSessionHas('cart_notices');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_estoque_caiu_durante_o_checkout_nao_cobra(): void
    {
        $this->fakeAsaas();
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 3]);
        $this->actingAs($this->buyer());
        $this->cartWith($variant, 3);

        $variant->update(['stock' => 1]);

        $this->post('/finalizar', $this->pickupPix(['expected_subtotal' => '150.00']))->assertRedirect('/finalizar');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_pedido_de_outra_pessoa_e_404(): void
    {
        $order = Order::factory()->create();
        $intruso = User::factory()->create();

        $this->actingAs($intruso)->get(route('orders.show', $order))->assertNotFound();
        $this->actingAs($intruso)->getJson(route('orders.status', $order))->assertNotFound();
        $this->actingAs($order->user)->getJson(route('orders.status', $order))->assertOk()->assertJson(['paid' => false]);
    }
}
