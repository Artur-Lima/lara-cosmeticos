<?php

namespace Tests\Feature\Catalog;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingZone;
use Database\Seeders\CatalogBaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_e_gerado_e_unico_inclusive_contra_excluidos(): void
    {
        $a = Product::factory()->create(['name' => 'Kaiak Feminino']);
        $a->delete(); // soft delete mantém o slug reservado
        $b = Product::factory()->create(['name' => 'Kaiak Feminino']);
        $c = Product::factory()->create(['name' => 'Kaiak Feminino']);

        $this->assertSame('kaiak-feminino', $a->slug);
        $this->assertSame('kaiak-feminino-2', $b->slug);
        $this->assertSame('kaiak-feminino-3', $c->slug);
    }

    public function test_slug_informado_manualmente_e_respeitado_e_nao_muda_ao_editar_nome(): void
    {
        $brand = Brand::factory()->create(['name' => 'O Boticário', 'slug' => 'boticario']);

        $brand->update(['name' => 'O Boticário Oficial']);

        $this->assertSame('boticario', $brand->fresh()->slug);
    }

    public function test_vitrine_mostra_so_publicado_com_variante_ativa(): void
    {
        $publicado = Product::factory()->has(ProductVariant::factory(), 'variants')->create();
        Product::factory()->draft()->has(ProductVariant::factory(), 'variants')->create();
        Product::factory()->create(); // sem variante
        Product::factory()->has(ProductVariant::factory()->inactive(), 'variants')->create();

        $visiveis = Product::visible()->pluck('id');

        $this->assertEquals([$publicado->id], $visiveis->all());
        $this->assertTrue($publicado->isVisible());
    }

    public function test_variante_padrao_e_faixa_de_preco(): void
    {
        $product = Product::factory()->withVolumes([30, 75, 100])->create();

        $this->assertSame('75 ml', $product->defaultVariant->label);
        $this->assertSame([76.0, 160.0], $product->priceRange());

        // Sem default marcado, cai na primeira ativa por posição.
        $product->variants()->update(['is_default' => false]);
        $this->assertSame('30 ml', $product->fresh()->defaultVariant->label);
    }

    public function test_faixa_de_preco_usa_relacao_carregada_sem_nova_query(): void
    {
        Product::factory()->withVolumes()->count(3)->create();
        $products = Product::with('activeVariants')->get();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $products->each->priceRange();

        $this->assertSame(0, $queries);
    }

    public function test_status_e_genero_sao_enums(): void
    {
        $product = Product::factory()->create(['status' => 'archived']);

        $this->assertSame(ProductStatus::Archived, $product->fresh()->status);
    }

    public function test_frete_gratis_acima_do_valor_da_zona(): void
    {
        $zona = ShippingZone::factory()->create(['fee' => 8, 'free_above' => 150]);
        $semGratis = ShippingZone::factory()->create(['fee' => 5, 'free_above' => null]);

        $this->assertSame(8.0, $zona->feeFor(149.99));
        $this->assertSame(0.0, $zona->feeFor(150));
        $this->assertSame(5.0, $semGratis->feeFor(1000));
    }

    public function test_seed_base_e_idempotente(): void
    {
        $this->seed(CatalogBaseSeeder::class);
        $this->seed(CatalogBaseSeeder::class);

        $this->assertSame(3, Brand::count());
        $this->assertSame(6, Category::roots()->count());
        $this->assertSame(
            ['perfumes-femininos', 'perfumes-masculinos', 'body-splash'],
            Category::where('slug', 'perfumaria')->first()->children->pluck('slug')->all(),
        );
    }

    public function test_excluir_produto_remove_variantes_em_cascata_no_force_delete(): void
    {
        $product = Product::factory()->withVolumes()->create();

        $product->forceDelete();

        $this->assertSame(0, ProductVariant::withTrashed()->count());
    }
}
