<?php

namespace Tests\Feature\Store;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demonstracao_mostra_aviso_e_bloqueia_buscadores(): void
    {
        config(['shop.demo' => true]);

        $this->get('/')->assertOk()
            ->assertSee('Loja em demonstração')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false);
    }

    public function test_loja_normal_sem_aviso_e_indexavel(): void
    {
        config(['shop.demo' => false]);

        $this->get('/')->assertOk()
            ->assertDontSee('Loja em demonstração')
            ->assertDontSee('noindex', false);

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertDontSee("Disallow: /\n", false);
    }
}
