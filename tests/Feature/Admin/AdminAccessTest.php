<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function adminPages(): array
    {
        return [
            ['/admin'],
            ['/admin/produtos'],
            ['/admin/produtos/create'],
            ['/admin/marcas'],
            ['/admin/categorias'],
            ['/admin/entrega'],
        ];
    }

    #[DataProvider('adminPages')]
    public function test_visitante_e_mandado_para_o_login(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    #[DataProvider('adminPages')]
    public function test_cliente_recebe_403(string $url): void
    {
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    #[DataProvider('adminPages')]
    public function test_admin_acessa(string $url): void
    {
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    }

    public function test_minha_conta_leva_admin_para_o_painel_e_cliente_para_a_conta(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/minha-conta')
            ->assertRedirect(route('admin.dashboard'));

        $this->actingAs(User::factory()->create())
            ->get('/minha-conta')
            ->assertOk()
            ->assertSee('Meus pedidos');
    }

    public function test_cliente_nao_consegue_criar_produto_via_post(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/produtos', ['name' => 'Hack'])
            ->assertForbidden();

        $this->assertDatabaseCount('products', 0);
    }
}
