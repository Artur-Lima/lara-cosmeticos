<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware('auth')->group(function () {
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

    Route::resource('entrega', Admin\ShippingZoneController::class)
        ->parameters(['entrega' => 'shippingZone'])
        ->names('shipping-zones')
        ->except('show');
});

require __DIR__.'/auth.php';
