<?php

namespace Tests\Feature\Admin;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Rules\Cpf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_cliente_nao_acessa_pedidos_do_admin(): void
    {
        $order = Order::factory()->create();

        $this->actingAs(User::factory()->create())->get('/admin/pedidos')->assertForbidden();
        $this->actingAs(User::factory()->create())->patch("/admin/pedidos/{$order->code}/status", ['status' => 'cancelado'])->assertForbidden();
    }

    public function test_lista_filtra_e_mostra_pedido(): void
    {
        $alerta = Order::factory()->create(['needs_attention' => true, 'attention_reason' => 'Sem estoque']);
        $normal = Order::factory()->create();

        $this->actingAs($this->admin)->get('/admin/pedidos?atencao=1')
            ->assertOk()->assertSee($alerta->code)->assertDontSee($normal->code);

        $this->actingAs($this->admin)->get("/admin/pedidos/{$alerta->code}")
            ->assertOk()->assertSee('Precisa de atenção')->assertSee('Sem estoque');
    }

    public function test_fluxo_de_retirada(): void
    {
        $order = Order::factory()->status(OrderStatus::Paid)->create(['delivery_method' => DeliveryMethod::Pickup]);
        $url = "/admin/pedidos/{$order->code}/status";

        $this->actingAs($this->admin)->patch($url, ['status' => 'em_separacao'])->assertSessionHas('success');
        // Retirada não pode "sair para entrega".
        $this->actingAs($this->admin)->patch($url, ['status' => 'saiu_entrega'])->assertSessionHas('error');
        $this->actingAs($this->admin)->patch($url, ['status' => 'pronto_retirada', 'note' => 'Avisada no zap'])->assertSessionHas('success');
        $this->actingAs($this->admin)->patch($url, ['status' => 'entregue'])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertSame(['em_separacao', 'pronto_retirada', 'entregue'], $order->history->pluck('to_status')->map->value->all());
        $this->assertSame($this->admin->id, $order->history->last()->user_id);
    }

    public function test_nao_pula_etapas_nem_reabre_entregue(): void
    {
        $aguardando = Order::factory()->create();
        $entregue = Order::factory()->status(OrderStatus::Delivered)->create();

        $this->actingAs($this->admin)->patch("/admin/pedidos/{$aguardando->code}/status", ['status' => 'pago'])->assertSessionHas('error');
        $this->actingAs($this->admin)->patch("/admin/pedidos/{$entregue->code}/status", ['status' => 'cancelado'])->assertSessionHas('error');

        $this->assertSame(OrderStatus::AwaitingPayment, $aguardando->fresh()->status);
    }

    public function test_cancelar_pedido_pago_devolve_estoque(): void
    {
        $variant = ProductVariant::factory()->create(['price' => 50, 'stock' => 3]);
        $order = Order::factory()->withItem($variant, 2)->status(OrderStatus::Paid)->create();
        $order->items()->update(['stock_deducted' => true]);

        $this->actingAs($this->admin)->patch("/admin/pedidos/{$order->code}/status", ['status' => 'cancelado', 'note' => 'Cliente desistiu'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'estorno'));

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(5, $variant->fresh()->stock);
    }

    public function test_resolver_alerta(): void
    {
        $order = Order::factory()->create(['needs_attention' => true, 'attention_reason' => 'Valor divergente']);

        $this->actingAs($this->admin)->patch("/admin/pedidos/{$order->code}/resolver")->assertSessionHas('success');

        $this->assertFalse($order->fresh()->needs_attention);
        $this->assertStringContainsString('Valor divergente', $order->history()->latest('id')->first()->note);
    }

    public function test_painel_mostra_pedidos_a_tratar_e_alertas(): void
    {
        Order::factory()->status(OrderStatus::Paid)->create();
        $alerta = Order::factory()->create(['needs_attention' => true, 'attention_reason' => 'Pago após cancelamento']);

        $this->actingAs($this->admin)->get('/admin')
            ->assertSee('Pedidos que precisam de atenção')
            ->assertSee($alerta->code);
    }

    public function test_regra_de_cpf(): void
    {
        $this->assertTrue(Cpf::isValid('52998224725'));
        $this->assertFalse(Cpf::isValid('52998224724'));
        $this->assertFalse(Cpf::isValid('11111111111'));
        $this->assertFalse(Cpf::isValid('123'));
        $this->assertSame('52998224725', Cpf::normalize('529.982.247-25'));
    }
}
