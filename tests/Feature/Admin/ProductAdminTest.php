<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = User::factory()->admin()->create();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'name' => 'Kaiak Aventura Feminino',
            'brand_id' => Brand::factory()->create()->id,
            'category_id' => Category::factory()->create()->id,
            'short_description' => 'Fresco e cítrico',
            'status' => 'active',
            'is_featured' => '1',
            'default_variant' => '1',
            'variants' => [
                ['sku' => 'nat-kaiak-50', 'label' => '50 ml', 'volume_ml' => '50', 'price' => '99.90', 'compare_at_price' => '', 'stock' => '4', 'is_active' => '1'],
                ['sku' => 'NAT-KAIAK-100', 'label' => '100 ml', 'volume_ml' => '100', 'price' => '149.90', 'compare_at_price' => '179.90', 'stock' => '2', 'is_active' => '1'],
            ],
        ], $overrides);
    }

    public function test_cadastra_produto_com_variantes_e_fotos(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/produtos', $this->payload() + [
            'images' => [UploadedFile::fake()->image('frente.jpg', 800, 800), UploadedFile::fake()->image('verso.png')],
        ]);

        $product = Product::sole();
        $response->assertRedirect(route('admin.products.edit', $product))->assertSessionHas('success');

        $this->assertSame('kaiak-aventura-feminino', $product->slug);
        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertTrue($product->is_featured);

        $this->assertSame(['NAT-KAIAK-50', 'NAT-KAIAK-100'], $product->variants->pluck('sku')->all());
        $this->assertSame('100 ml', $product->defaultVariant->label);
        $this->assertNull($product->variants[0]->compare_at_price);
        $this->assertSame('179.90', $product->variants[1]->compare_at_price);

        $this->assertCount(2, $product->images);
        $this->assertSame([1, 2], $product->images->pluck('position')->all());
        Storage::disk('public')->assertExists($product->images->pluck('path')->all());
    }

    public function test_edicao_atualiza_cria_e_remove_variantes(): void
    {
        $product = Product::factory()->withVolumes([30, 75, 100])->create();
        [$v30, $v75, $v100] = $product->variants;

        $this->actingAs($this->admin)->put("/admin/produtos/{$product->id}", $this->payload([
            'name' => $product->name,
            'default_variant' => '0',
            'variants' => [
                ['id' => $v75->id, 'sku' => $v75->sku, 'label' => '75 ml', 'price' => '120', 'stock' => '9', 'is_active' => '1'],
                ['id' => '', 'sku' => 'NOVO-200', 'label' => '200 ml', 'price' => '220', 'compare_at_price' => '', 'stock' => '1', 'is_active' => '0'],
            ],
        ]))->assertSessionHasNoErrors();

        $product->refresh();

        // 30 ml e 100 ml saíram do formulário: soft delete, não apagadas de vez.
        $this->assertSoftDeleted($v30);
        $this->assertSoftDeleted($v100);
        $this->assertSame(['75 ml', '200 ml'], $product->variants->pluck('label')->all());
        $this->assertSame(9, $v75->fresh()->stock);
        $this->assertSame('120.00', $v75->fresh()->price);
        $this->assertTrue($v75->fresh()->is_default);
        $this->assertFalse($product->variants[1]->is_active);
    }

    public function test_sku_ja_usado_em_outro_produto_e_recusado(): void
    {
        ProductVariant::factory()->create(['sku' => 'NAT-KAIAK-50']);

        $this->actingAs($this->admin)
            ->post('/admin/produtos', $this->payload())
            ->assertSessionHasErrors('variants.0.sku');

        $this->assertDatabaseCount('products', 1); // só o do factory
    }

    public function test_sku_de_variante_excluida_continua_reservado(): void
    {
        ProductVariant::factory()->create(['sku' => 'NAT-KAIAK-50'])->delete();

        $this->actingAs($this->admin)
            ->post('/admin/produtos', $this->payload())
            ->assertSessionHasErrors('variants.0.sku');
    }

    public function test_sku_repetido_no_mesmo_envio_e_recusado(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/produtos', $this->payload(['variants' => [1 => ['sku' => 'NAT-KAIAK-50']]]))
            ->assertSessionHasErrors(['variants.0.sku', 'variants.1.sku']);
    }

    public function test_preco_de_menor_ou_igual_ao_preco_e_recusado(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/produtos', $this->payload(['variants' => [1 => ['compare_at_price' => '149.90']]]))
            ->assertSessionHasErrors('variants.1.compare_at_price');
    }

    public function test_nao_permite_editar_variante_de_outro_produto(): void
    {
        $alheia = ProductVariant::factory()->create(['price' => 10]);
        $product = Product::factory()->withVolumes([50])->create();

        $this->actingAs($this->admin)->put("/admin/produtos/{$product->id}", $this->payload([
            'default_variant' => '0',
            'variants' => [
                ['id' => $alheia->id, 'sku' => $alheia->sku, 'label' => 'roubada', 'price' => '1', 'stock' => '0', 'is_active' => '1'],
            ],
        ]))->assertSessionHasErrors('variants.0.id');

        $this->assertSame('10.00', $alheia->fresh()->price);
    }

    public function test_exige_ao_menos_uma_variante_e_variante_padrao_valida(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/produtos', array_merge($this->payload(), ['variants' => []]))
            ->assertSessionHasErrors('variants');

        $this->actingAs($this->admin)
            ->post('/admin/produtos', $this->payload(['default_variant' => '7']))
            ->assertSessionHasErrors('default_variant');
    }

    public function test_recusa_arquivo_que_nao_e_imagem(): void
    {
        $this->actingAs($this->admin)
            ->post('/admin/produtos', $this->payload() + ['images' => [UploadedFile::fake()->create('virus.php', 10, 'application/x-php')]])
            ->assertSessionHasErrors('images.0');

        $this->assertDatabaseCount('products', 0);
    }

    public function test_remove_foto_do_produto(): void
    {
        $product = Product::factory()->create();
        Storage::disk('public')->put('products/1/a.webp', 'x');
        $image = ProductImage::factory()->for($product)->create(['path' => 'products/1/a.webp']);

        $this->actingAs($this->admin)
            ->delete("/admin/produtos/{$product->id}/fotos/{$image->id}")
            ->assertRedirect();

        $this->assertModelMissing($image);
        Storage::disk('public')->assertMissing('products/1/a.webp');
    }

    public function test_nao_remove_foto_por_url_de_outro_produto(): void
    {
        $image = ProductImage::factory()->create();
        $outro = Product::factory()->create();

        $this->actingAs($this->admin)
            ->delete("/admin/produtos/{$outro->id}/fotos/{$image->id}")
            ->assertNotFound();

        $this->assertModelExists($image);
    }

    public function test_excluir_produto_e_soft_delete(): void
    {
        $product = Product::factory()->withVolumes()->create();

        $this->actingAs($this->admin)->delete("/admin/produtos/{$product->id}")
            ->assertRedirect(route('admin.products.index'));

        $this->assertSoftDeleted($product);
        $this->assertSame(3, ProductVariant::count()); // variantes intactas para pedidos antigos
    }

    public function test_listagem_busca_por_sku_e_filtra_esgotados(): void
    {
        $kaiak = Product::factory()->has(ProductVariant::factory()->state(['sku' => 'NAT-KAIAK-50', 'stock' => 3]), 'variants')->create(['name' => 'Kaiak']);
        $esgotado = Product::factory()->has(ProductVariant::factory()->outOfStock(), 'variants')->create(['name' => 'Malbec']);

        $this->actingAs($this->admin)->get('/admin/produtos?q=kaiak-50')
            ->assertOk()->assertSee('Kaiak')->assertDontSee('Malbec');

        $this->actingAs($this->admin)->get('/admin/produtos?sem_estoque=1')
            ->assertOk()->assertSee('Malbec')->assertDontSee('Kaiak');
    }

    public function test_formulario_de_edicao_renderiza_com_variantes(): void
    {
        $product = Product::factory()->withVolumes([30, 100])->create();

        $this->actingAs($this->admin)->get("/admin/produtos/{$product->id}/edit")
            ->assertOk()
            ->assertSee($product->variants[0]->sku)
            ->assertSee('variantEditor', false);
    }
}
