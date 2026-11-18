<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Store;
use App\Http\Controllers\Webhooks\AsaasWebhookController;
use Illuminate\Support\Facades\Route;

// Vitrine (pública)
Route::get('/', Store\HomeController::class)->name('home');
Route::get('/produtos', [Store\CatalogController::class, 'index'])->name('catalog');
Route::get('/categoria/{category:slug}', [Store\CatalogController::class, 'category'])->name('catalog.category');
Route::get('/marca/{brand:slug}', [Store\CatalogController::class, 'brand'])->name('catalog.brand');
Route::get('/produto/{product:slug}', [Store\ProductController::class, 'show'])->name('product.show');

// Sacola (sessão; login só será exigido no checkout)
Route::get('/sacola', [Store\CartController::class, 'index'])->name('cart.index');
Route::middleware('throttle:60,1')->group(function () {
    Route::post('/sacola', [Store\CartController::class, 'store'])->name('cart.store');
    Route::patch('/sacola/{variant}', [Store\CartController::class, 'update'])->name('cart.update');
    Route::delete('/sacola/{variant}', [Store\CartController::class, 'destroy'])->whereNumber('variant')->name('cart.destroy');
});

// Webhook do Asaas (sem CSRF; autenticado pelo header asaas-access-token)
Route::post('/api/asaas/webhook', AsaasWebhookController::class)->name('webhooks.asaas');

Route::middleware('auth')->group(function () {
    Route::get('/finalizar', [Store\CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/finalizar', [Store\CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');

    Route::get('/minha-conta/pedidos', [Store\OrderController::class, 'index'])->name('orders.index');
    Route::get('/pedidos/{order}', [Store\OrderController::class, 'show'])->name('orders.show');
    Route::get('/pedidos/{order}/status', [Store\OrderController::class, 'status'])->middleware('throttle:30,1')->name('orders.status');

    // Admin cai direto no painel; cliente vê "Minha conta" (pedidos entram no Sprint 3).
    Route::get('/minha-conta', function () {
        return auth()->user()->isAdmin()
            ? redirect()->route('admin.dashboard')
            : view('dashboard');
    })->name('dashboard');

    Route::get('/minha-conta/dados', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/minha-conta/dados', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/minha-conta/dados', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::resource('produtos', Admin\ProductController::class)
        ->parameters(['produtos' => 'product'])
        ->names('products')
        ->except('show');
    Route::delete('produtos/{product}/fotos/{image}', [Admin\ProductController::class, 'destroyImage'])
        ->scopeBindings()
        ->name('products.images.destroy');

    Route::resource('marcas', Admin\BrandController::class)
        ->parameters(['marcas' => 'brand'])
        ->names('brands')
        ->except('show');

    Route::resource('categorias', Admin\CategoryController::class)
        ->parameters(['categorias' => 'category'])
        ->names('categories')
        ->except('show');

    Route::get('pedidos', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('pedidos/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::patch('pedidos/{order}/status', [Admin\OrderController::class, 'updateStatus'])->name('orders.status');
    Route::patch('pedidos/{order}/resolver', [Admin\OrderController::class, 'resolveAttention'])->name('orders.resolve');

    Route::resource('entrega', Admin\ShippingZoneController::class)
        ->parameters(['entrega' => 'shippingZone'])
        ->names('shipping-zones')
        ->except('show');
});

require __DIR__.'/auth.php';
