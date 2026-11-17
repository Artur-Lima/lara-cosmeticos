<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSupportAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_crud_de_marca_e_bloqueio_de_exclusao_com_produtos(): void
    {
        $this->actingAs($this->admin)->post('/admin/marcas', ['name' => 'Eudora', 'is_active' => '1'])
            ->assertRedirect('/admin/marcas');
        $brand = Brand::sole();
        $this->assertSame('eudora', $brand->slug);

        $this->actingAs($this->admin)->put("/admin/marcas/{$brand->id}", ['name' => 'Eudora Oficial'])
            ->assertRedirect('/admin/marcas');
        $this->assertFalse($brand->fresh()->is_active); // checkbox desmarcado

        Product::factory()->for($brand)->create();
        $this->actingAs($this->admin)->delete("/admin/marcas/{$brand->id}")->assertSessionHas('error');
        $this->assertModelExists($brand);
    }

    public function test_marca_com_nome_repetido_e_recusada(): void
    {
        Brand::factory()->create(['name' => 'Natura']);

        $this->actingAs($this->admin)->post('/admin/marcas', ['name' => 'Natura'])->assertSessionHasErrors('name');
    }

    public function test_categoria_aceita_so_dois_niveis(): void
    {
        $raiz = Category::factory()->create();
        $filha = Category::factory()->childOf($raiz)->create();

        // Não pode criar neta.
        $this->actingAs($this->admin)->post('/admin/categorias', ['name' => 'Neta', 'parent_id' => $filha->id])
            ->assertSessionHasErrors('parent_id');

        // Categoria com filhas não pode virar filha.
        $outraRaiz = Category::factory()->create();
        $this->actingAs($this->admin)->put("/admin/categorias/{$raiz->id}", ['name' => $raiz->name, 'parent_id' => $outraRaiz->id])
            ->assertSessionHasErrors('parent_id');

        // Não pode ser pai de si mesma.
        $this->actingAs($this->admin)->put("/admin/categorias/{$outraRaiz->id}", ['name' => 'X', 'parent_id' => $outraRaiz->id])
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($this->admin)->post('/admin/categorias', ['name' => 'Kits de Natal', 'parent_id' => $raiz->id, 'is_active' => '1'])
            ->assertRedirect('/admin/categorias');
        $this->assertSame($raiz->id, Category::where('slug', 'kits-de-natal')->value('parent_id'));
    }

    public function test_categoria_com_filhas_nao_pode_ser_excluida(): void
    {
        $raiz = Category::factory()->create();
        Category::factory()->childOf($raiz)->create();

        $this->actingAs($this->admin)->delete("/admin/categorias/{$raiz->id}")->assertSessionHas('error');
        $this->assertModelExists($raiz);
    }

    public function test_crud_de_zona_de_entrega(): void
    {
        $this->actingAs($this->admin)->post('/admin/entrega', [
            'name' => 'Centro', 'fee' => '5', 'free_above' => '150', 'estimate' => 'até 2h', 'is_active' => '1',
        ])->assertRedirect('/admin/entrega');

        $zone = ShippingZone::sole();
        $this->assertSame(0.0, $zone->feeFor(150));

        $this->actingAs($this->admin)->post('/admin/entrega', ['name' => 'Centro', 'fee' => '5'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->admin)->post('/admin/entrega', ['name' => 'Rural', 'fee' => '-1'])
            ->assertSessionHasErrors('fee');

        $this->actingAs($this->admin)->delete("/admin/entrega/{$zone->id}")->assertRedirect('/admin/entrega');
        $this->assertModelMissing($zone);
    }

    public function test_painel_avisa_quando_nao_ha_zona_de_entrega(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSee('Nenhuma região de entrega cadastrada');

        ShippingZone::factory()->create();

        $this->actingAs($this->admin)->get('/admin')->assertDontSee('Nenhuma região de entrega cadastrada');
    }
}
