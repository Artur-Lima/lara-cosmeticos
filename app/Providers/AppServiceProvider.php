<?php

namespace App\Providers;

use App\Models\Category;
use App\Services\Asaas\AsaasClient;
use App\Services\Cart\Cart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Instância nova a cada resolução: o Cart memoriza as linhas calculadas,
        // e um singleton/scoped serviria preço antigo em processos de vida longa
        // (testes, Octane). O estado de verdade fica só na sessão.
        $this->app->bind(Cart::class, fn ($app) => new Cart($app['session.store']));

        $this->app->bind(AsaasClient::class, fn () => AsaasClient::fromConfig());
    }

    public function boot(): void
    {
        Paginator::defaultView('pagination::tailwind');

        View::composer('components.store-layout', function ($view) {
            $view->with('cartCount', app(Cart::class)->count());

            // Menu muda pouco: cache curto, invalidado quando categoria é salva.
            $view->with('menuCategories', Cache::remember(Category::MENU_CACHE_KEY, 600, fn () => Category::active()->roots()
                ->where(fn ($q) => $q
                    ->whereHas('products', fn ($p) => $p->visible())
                    ->orWhereHas('children.products', fn ($p) => $p->visible()))
                ->orderBy('position')
                ->get(['id', 'name', 'slug'])));
        });
    }
}
