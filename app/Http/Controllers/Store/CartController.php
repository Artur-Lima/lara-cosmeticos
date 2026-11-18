<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Cart\Cart;
use App\Services\Cart\CartException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    // Cart injetado por método (não no construtor): o Laravel reutiliza a
    // instância do controller na rota, e o Cart memoriza as linhas calculadas.

    public function index(Cart $cart): View
    {
        $lines = $cart->lines(); // reconcilia com o banco antes de exibir

        return view('store.cart', [
            'lines' => $lines,
            'subtotal' => $cart->subtotal(),
            'notices' => $cart->notices(),
        ]);
    }

    public function store(Request $request, Cart $cart): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $variant = ProductVariant::with('product')->find($data['variant_id']);

        if (! $variant) {
            return back()->with('error', 'Este produto não está disponível para compra.');
        }

        try {
            $cart->add($variant, (int) ($data['quantity'] ?? 1));
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()
            ->with('success', 'Adicionado à sacola.')
            ->with('cart_added', $variant->id)
            ->with('cart_notices', $cart->notices());
    }

    public function update(Request $request, ProductVariant $variant, Cart $cart): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:99']]);

        try {
            $cart->update($variant->load('product'), (int) $data['quantity']);
        } catch (CartException $e) {
            $cart->remove($variant->id);

            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        return redirect()->route('cart.index')->with('cart_notices', $cart->notices());
    }

    public function destroy(Cart $cart, int $variant): RedirectResponse
    {
        $cart->remove($variant);

        return redirect()->route('cart.index')->with('success', 'Item removido da sacola.');
    }
}
