<?php

namespace Tests\Feature\Store;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attributes = [], array $variant = []): Product
    {
        return Product::factory()
            ->has(ProductVariant::factory()->state($variant + ['is_default' => true]), 'variants')
            ->create($attributes);
    }

    public function test_home_abre_vazia_e_com_produtos(): void
    {
        $this->get('/')->assertOk()->assertSee('A loja está sendo abastecida');

        $this->product(['name' => 'Kaiak Aventura', 'is_featured' => true]);

        $this->get('/')->assertOk()->assertSee('Kaiak Aventura')->assertSee('Escolhas da Lara');
    }

    public function test_home_nao_mostra_rascunho(): void
    {
        $this->product(['name' => 'Segredo Rascunho', 'status' => 'draft', 'is_featured' => true]);

        $this->get('/')->assertOk()->assertDontSee('Segredo Rascunho');
    }

    public function test_home_e_catalogo_tem_numero_fixo_de_queries(): void
    {
        Product::factory()->withVolumes()->count(3)->create(['is_featured' => true]);

        DB::enableQueryLog();
        $this->get('/produtos')->assertOk();
        $poucos = count(DB::getQueryLog());

        Product::factory()->withVolumes()->count(12)->create();
        DB::flushQueryLog();
        $this->get('/produtos')->assertOk();

        $this->assertSame($poucos, count(DB::getQueryLog()), 'Listagem com N+1: queries crescem com o número de produtos.');
    }

    public function test_pagina_do_produto_e_404_para_rascunho_e_previa_para_admin(): void
    {
        $draft = $this->product(['status' => 'draft']);

        $this->get("/produto/{$draft->slug}")->assertNotFound();
        $this->actingAs(User::factory()->create())->get("/produto/{$draft->slug}")->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get("/produto/{$draft->slug}")
            ->assertOk()->assertSee('Prévia: este produto não aparece na loja');
    }

    public function test_pagina_do_produto_mostra_variacoes_preco_promocional_e_json_ld(): void
    {
        $product = Product::factory()->create(['name' => 'Malbec Gold']);
        ProductVariant::factory()->for($product)->onSale(199.90, 159.90)->create(['label' => '100 ml', 'sku' => 'MAL-100', 'is_default' => true]);
        ProductVariant::factory()->for($product)->create(['label' => '50 ml', 'price' => 99.90, 'sku' => 'MAL-50']);
        ProductVariant::factory()->for($product)->inactive()->create(['label' => '30 ml oculto']);

        $this->get("/produto/{$product->slug}")
            ->assertOk()
            ->assertSee('Malbec Gold')
            ->assertSee('R$ 159,90')
            ->assertSee('R$ 199,90')
            ->assertSee('100 ml')
            ->assertSee('50 ml')
            ->assertDontSee('30 ml oculto')
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"priceCurrency":"BRL"', false);
    }

    public function test_variante_inativa_nao_pode_ser_escolhida_pela_url(): void
    {
        $product = $this->product([], ['label' => '75 ml']);
        $inativa = ProductVariant::factory()->for($product)->inactive()->create(['label' => 'Inativa']);

        $this->get("/produto/{$product->slug}?variante={$inativa->id}")->assertOk()->assertDontSee('Inativa');
    }

    public function test_categoria_inclui_subcategorias_e_esconde_inativa(): void
    {
        $perfumaria = Category::factory()->create(['name' => 'Perfumaria']);
        $femininos = Category::factory()->childOf($perfumaria)->create(['name' => 'Perfumes Femininos']);
        $this->product(['name' => 'Ilía', 'category_id' => $femininos->id]);
        $this->product(['name' => 'Batom fora']);

        $this->get('/categoria/perfumaria')->assertOk()->assertSee('Ilía')->assertDontSee('Batom fora')->assertSee('Perfumes Femininos');

        $perfumaria->update(['is_active' => false]);
        $this->get('/categoria/perfumaria')->assertNotFound();
    }

    public function test_busca_por_nome_e_marca(): void
    {
        $natura = Brand::factory()->create(['name' => 'Natura']);
        $this->product(['name' => 'Ekos Maracujá', 'brand_id' => $natura->id]);
        $this->product(['name' => 'Floratta Blue']);

        $this->get('/produtos?busca=maracuj')->assertOk()->assertSee('Ekos Maracujá')->assertDontSee('Floratta Blue');
        $this->get('/produtos?busca=natura')->assertOk()->assertSee('Ekos Maracujá')->assertDontSee('Floratta Blue');
        $this->get('/produtos?busca=inexistente')->assertOk()->assertSee('Nenhum produto encontrado');
    }

    public function test_busca_trata_curinga_do_like_como_texto(): void
    {
        $this->product(['name' => 'Perfume Comum']);

        $this->get('/produtos?busca=%25')->assertOk()->assertSee('Nenhum produto encontrado');
    }

    public function test_filtro_de_publico_inclui_unissex(): void
    {
        $this->product(['name' => 'Kaiak Feminino', 'gender' => 'feminino']);
        $this->product(['name' => 'Kaiak Masculino', 'gender' => 'masculino']);
        $this->product(['name' => 'Essencial Unissex', 'gender' => 'unissex']);

        $this->get('/produtos?publico=feminino')->assertOk()
            ->assertSee('Kaiak Feminino')->assertSee('Essencial Unissex')->assertDontSee('Kaiak Masculino');
    }

    public function test_ordena_por_menor_e_maior_preco(): void
    {
        $this->product(['name' => 'Caro'], ['price' => 300]);
        $this->product(['name' => 'Barato'], ['price' => 20]);
        $this->product(['name' => 'Medio'], ['price' => 100]);

        $this->get('/produtos?ordem=menor-preco')->assertSeeInOrder(['Barato', 'Medio', 'Caro']);
        $this->get('/produtos?ordem=maior-preco')->assertSeeInOrder(['Caro', 'Medio', 'Barato']);
    }

    public function test_filtro_invalido_na_url_e_ignorado(): void
    {
        $this->product(['name' => 'Visivel']);

        $this->get('/produtos?ordem=hack&publico=xyz&marca[]=1')->assertOk()->assertSee('Visivel');
    }

    public function test_card_mostra_a_partir_de_com_menor_preco(): void
    {
        $product = Product::factory()->create(['name' => 'Egeo']);
        ProductVariant::factory()->for($product)->create(['price' => 189.90, 'is_default' => true]);
        ProductVariant::factory()->for($product)->create(['price' => 89.90]);

        $this->get('/produtos')->assertSee('a partir de')->assertSee('R$ 89,90')->assertSee('2 tamanhos');
    }
}
