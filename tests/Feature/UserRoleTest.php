<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_novo_usuario_e_cliente(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Cliente, $user->fresh()->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_role_nao_pode_ser_definido_por_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Invasor',
            'email' => 'invasor@example.com',
            'password' => 'secret123',
            'role' => 'admin',
        ]);

        $this->assertSame(UserRole::Cliente, $user->fresh()->role);
    }

    public function test_comando_promove_e_rebaixa_admin(): void
    {
        $user = User::factory()->create(['email' => 'lara@example.com']);

        $this->artisan('user:make-admin', ['email' => 'lara@example.com'])->assertSuccessful();
        $this->assertTrue($user->fresh()->isAdmin());

        $this->artisan('user:make-admin', ['email' => 'lara@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_comando_falha_para_email_inexistente(): void
    {
        $this->artisan('user:make-admin', ['email' => 'ninguem@example.com'])->assertFailed();
    }

    public function test_cpf_nao_aparece_na_serializacao(): void
    {
        $user = User::factory()->create(['cpf' => '12345678909']);

        $this->assertArrayNotHasKey('cpf', $user->toArray());
    }
}
