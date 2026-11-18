<?php

namespace App\Http\Controllers\Store;

use App\Actions\Checkout\PlaceOrder;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Models\ShippingZone;
use App\Services\Asaas\AsaasException;
use App\Services\Cart\Cart;
use App\Services\Cart\CartException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function show(Request $request, Cart $cart): View|RedirectResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Sua sacola está vazia.');
        }

        $lastAddress = $request->user()->orders()->whereNotNull('address')->value('address');

        return view('store.checkout', [
            'lines' => $lines,
            'subtotal' => $cart->subtotal(),
            'notices' => $cart->notices(),
            'zones' => ShippingZone::active()->get(),
            'lastAddress' => $lastAddress,
            'needsCpf' => blank($request->user()->cpf),
        ]);
    }

    public function store(CheckoutRequest $request, PlaceOrder $placeOrder): RedirectResponse
    {
        $user = $request->user();

        if (blank($user->cpf)) {
            $user->cpf = $request->validated('cpf');
            $user->save();
        }

        try {
            $order = $placeOrder->handle($user, $request->validated());
        } catch (CartException $e) {
            return redirect()->route('checkout.show')->with('cart_notices', [$e->getMessage()])->withInput();
        } catch (AsaasException $e) {
            report($e);

            return redirect()->route('checkout.show')->withInput()
                ->with('error', 'Não conseguimos gerar o pagamento agora. Sua sacola continua salva: tente de novo em instantes.');
        }

        if ($order->payment_method === PaymentMethod::CreditCard && $order->payment_url) {
            // Cartão é digitado na página segura do Asaas: o número nunca passa pelo nosso servidor.
            return redirect()->away($order->payment_url);
        }

        return redirect()->route('orders.show', $order);
    }
}
