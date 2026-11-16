<?php

namespace Tests\Feature\Catalog;

use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ProductVariantStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_baixa_estoque_quando_ha_quantidade_suficiente(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        $this->assertTrue($variant->decrementStock(3));
        $this->assertSame(2, $variant->stock);
        $this->assertSame(2, $variant->fresh()->stock);
    }

    public function test_recusa_baixa_maior_que_o_estoque_sem_alterar_nada(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 2]);

        $this->assertFalse($variant->decrementStock(3));
        $this->assertSame(2, $variant->fresh()->stock);
    }

    public function test_ultimo_item_so_pode_ser_baixado_uma_vez(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 1]);

        // Duas instâncias simulam duas confirmações de pagamento concorrentes
        // que leram o mesmo estoque (1) antes de baixar.
        $pagamentoA = ProductVariant::find($variant->id);
        $pagamentoB = ProductVariant::find($variant->id);

        $this->assertTrue($pagamentoA->decrementStock(1));
        $this->assertFalse($pagamentoB->decrementStock(1));
        $this->assertSame(0, $variant->fresh()->stock);
    }

    public function test_devolve_estoque(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 0]);

        $variant->incrementStock(2);

        $this->assertSame(2, $variant->fresh()->stock);
    }

    public function test_quantidade_invalida_e_rejeitada(): void
    {
        $variant = ProductVariant::factory()->create(['stock' => 5]);

        $this->expectException(InvalidArgumentException::class);
        $variant->decrementStock(0);
    }

    public function test_in_stock_considera_variante_inativa_como_indisponivel(): void
    {
        $ativa = ProductVariant::factory()->create(['stock' => 3]);
        $inativa = ProductVariant::factory()->inactive()->create(['stock' => 3]);

        $this->assertTrue($ativa->inStock(3));
        $this->assertFalse($ativa->inStock(4));
        $this->assertFalse($inativa->inStock());
    }

    public function test_desconto_sobre_preco_de(): void
    {
        $promo = ProductVariant::factory()->onSale(189.90, 149.90)->create();
        $normal = ProductVariant::factory()->create(['price' => 100, 'compare_at_price' => null]);
        $de_menor = ProductVariant::factory()->create(['price' => 100, 'compare_at_price' => 90]);

        $this->assertTrue($promo->isOnSale());
        $this->assertSame(21, $promo->discountPercent());
        $this->assertNull($normal->discountPercent());
        $this->assertFalse($de_menor->isOnSale());
    }
}
