<?php

namespace Tests\Feature\Store;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private function variant(array $attributes = []): ProductVariant
    {
        return ProductVariant::factory()->create($attributes + ['price' => 50, 'stock' => 5]);
    }

    public function test_adiciona_e_mostra_na_sacola_com_preco_do_banco(): void
    {
        $variant = $this->variant(['price' => 79.90]);

        $this->post('/sacola', ['variant_id' => $variant->id, 'quantity' => 2])
            ->assertRedirect()
            ->assertSessionHas('success', 'Adicionado à sacola.');

        $this->get('/sacola')->assertOk()->assertSee('R$ 159,80');

        // Loja muda o preço: a sacola reflete na hora.
        $variant->update(['price' => 69.90]);
        $this->get('/sacola')->assertSee('R$ 139,80')->assertDontSee('R$ 159,80');
    }

    public function test_quantidade_e_limitada_ao_estoque_com_aviso(): void
    {
        $variant = $this->variant(['stock' => 3]);

        $this->post('/sacola', ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post('/sacola', ['variant_id' => $variant->id, 'quantity' => 5])
            ->assertSessionHas('cart_notices', fn ($n) => str_contains($n[0], 'Só temos 3 unidades'));

        $this->assertSame([$variant->id => 3], session('cart.items'));
    }

    public function test_quantidade_limitada_pelo_maximo_por_item(): void
    {
        config(['shop.max_qty_per_item' => 4]);
        $variant = $this->variant(['stock' => 50]);

        $this->post('/sacola', ['variant_id' => $variant->id, 'quantity' => 20]);

        $this->assertSame([$variant->id => 4], session('cart.items'));
    }

    public function test_nao_adiciona_esgotado_inativo_rascunho_ou_inexistente(): void
    {
        $esgotado = $this->variant(['stock' => 0]);
        $inativa = $this->variant(['is_active' => false]);
        $rascunho = ProductVariant::factory()->for(Product::factory()->draft())->create();

        $this->post('/sacola', ['variant_id' => $esgotado->id])->assertSessionHas('error', fn ($m) => str_contains($m, 'esgotado'));
        $this->post('/sacola', ['variant_id' => $inativa->id])->assertSessionHas('error');
        $this->post('/sacola', ['variant_id' => $rascunho->id])->assertSessionHas('error');
        $this->post('/sacola', ['variant_id' => 999999])->assertSessionHas('error');

        $this->assertEmpty(session('cart.items', []));
    }

    public function test_reconcilia_quando_estoque_cai_ou_produto_sai_de_linha(): void
    {
        $a = $this->variant(['stock' => 5]);
        $b = $this->variant(['stock' => 5]);

        $this->post('/sacola', ['variant_id' => $a->id, 'quantity' => 4]);
        $this->post('/sacola', ['variant_id' => $b->id, 'quantity' => 1]);

        $a->update(['stock' => 2]);   // vendeu na loja física
        $b->product->delete();        // Lara tirou o produto do site

        $this->get('/sacola')
            ->assertOk()
            ->assertSee('para 2 unidades')
            ->assertSee('não está mais disponível');

        $this->assertSame([$a->id => 2], session('cart.items'));
    }

    public function test_atualiza_e_remove_item(): void
    {
        $variant = $this->variant(['stock' => 5]);
        $this->post('/sacola', ['variant_id' => $variant->id]);

        $this->patch("/sacola/{$variant->id}", ['quantity' => 3])->assertRedirect('/sacola');
        $this->assertSame([$variant->id => 3], session('cart.items'));

        $this->patch("/sacola/{$variant->id}", ['quantity' => 0]);
        $this->assertSame([], session('cart.items'));

        $this->post('/sacola', ['variant_id' => $variant->id]);
        $this->delete("/sacola/{$variant->id}")->assertRedirect('/sacola');
        $this->assertSame([], session('cart.items'));
    }

    public function test_contador_da_sacola_aparece_no_cabecalho(): void
    {
        $variant = $this->variant();
        $this->post('/sacola', ['variant_id' => $variant->id, 'quantity' => 2]);

        $this->get('/')->assertSee('aria-label="2 itens"', false);
    }

    public function test_sacola_vazia(): void
    {
        $this->get('/sacola')->assertOk()->assertSee('Sua sacola está vazia');
    }
}
