<?php

namespace App\Actions\Checkout;

use App\Enums\DeliveryMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\ShippingZone;
use App\Models\User;
use App\Services\Asaas\AsaasClient;
use App\Services\Asaas\AsaasException;
use App\Services\Cart\Cart;
use App\Services\Cart\CartException;
use App\Services\Cart\CartLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fecha o pedido: valida a sacola contra o banco, grava o pedido e gera a
 * cobrança no Asaas. A sacola só é esvaziada se a cobrança for criada.
 * O estoque NÃO é baixado aqui (ADR-005): só na confirmação do pagamento.
 */
class PlaceOrder
{
    public function __construct(
        private readonly Cart $cart,
        private readonly AsaasClient $asaas,
    ) {}

    /**
     * @param  array{delivery_method: string, payment_method: string, shipping_zone_id?: int|null,
     *     street?: string|null, number?: string|null, complement?: string|null, reference?: string|null,
     *     notes?: string|null, expected_subtotal?: numeric-string|float}  $data
     *
     * @throws CartException quando a sacola mudou ou ficou vazia (cliente precisa revisar)
     * @throws AsaasException quando o Asaas não gerou a cobrança (pedido fica cancelado)
     */
    public function handle(User $user, array $data): Order
    {
        $lines = $this->cart->lines();

        if ($this->cart->notices() !== []) {
            // Preço/estoque mudou desde que o cliente viu a sacola: não cobrar valor diferente sem ele ver.
            throw new CartException(implode(' ', $this->cart->notices()));
        }
        if ($lines->isEmpty()) {
            throw new CartException('Sua sacola está vazia.');
        }

        $delivery = DeliveryMethod::from($data['delivery_method']);
        $payment = PaymentMethod::from($data['payment_method']);
        $subtotal = $this->cart->subtotal();

        if (isset($data['expected_subtotal']) && round((float) $data['expected_subtotal'], 2) !== round($subtotal, 2)) {
            throw new CartException('O valor de algum produto mudou enquanto você finalizava. Confira o novo total.');
        }

        $zone = $delivery === DeliveryMethod::Delivery
            ? ShippingZone::where('is_active', true)->findOrFail($data['shipping_zone_id'])
            : null;
        $shippingFee = $zone?->feeFor($subtotal) ?? 0.0;

        $order = DB::transaction(function () use ($user, $data, $lines, $delivery, $payment, $subtotal, $zone, $shippingFee) {
            $order = Order::create([
                'code' => Order::generateCode(),
                'user_id' => $user->id,
                'status' => OrderStatus::AwaitingPayment,
                'delivery_method' => $delivery,
                'shipping_zone_id' => $zone?->id,
                'customer' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'cpf' => $user->cpf,
                ],
                'address' => $zone ? [
                    'street' => $data['street'],
                    'number' => $data['number'],
                    'complement' => $data['complement'] ?? null,
                    'neighborhood' => $zone->name,
                    'reference' => $data['reference'] ?? null,
                    'city' => ShippingZone::CITY,
                    'state' => ShippingZone::STATE,
                ] : null,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total' => round($subtotal + $shippingFee, 2),
                'payment_method' => $payment,
                'notes' => $data['notes'] ?? null,
            ]);

            $order->items()->createMany($lines->map(fn (CartLine $line) => [
                'product_variant_id' => $line->variant->id,
                'product_name' => $line->product()->name,
                'variant_label' => $line->variant->label,
                'sku' => $line->variant->sku,
                'unit_price' => $line->unitPrice(),
                'quantity' => $line->quantity,
                'total' => $line->total(),
            ])->all());

            $order->history()->create(['to_status' => OrderStatus::AwaitingPayment->value, 'note' => 'Pedido criado.']);

            return $order;
        });

        try {
            $this->createCharge($order, $user, $payment);
        } catch (AsaasException $e) {
            $order->transitionTo(OrderStatus::Cancelled, 'Falha ao gerar cobrança: '.$e->getMessage());

            throw $e;
        }

        $this->cart->clear();

        return $order;
    }

    private function createCharge(Order $order, User $user, PaymentMethod $payment): void
    {
        $customerId = $this->asaas->ensureCustomer($user);

        $charge = $this->asaas->createPayment([
            'customer' => $customerId,
            'billingType' => $payment->asaasBillingType(),
            'value' => (float) $order->total,
            'dueDate' => Carbon::today()->toDateString(),
            'description' => "Pedido {$order->code} - ".config('shop.name'),
            'externalReference' => $order->code,
        ]);

        $order->asaas_payment_id = $charge['id'];
        $order->payment_url = $charge['invoiceUrl'] ?? null;

        if ($payment === PaymentMethod::Pix) {
            $qr = $this->asaas->pixQrCode($charge['id']);
            $order->pix_payload = $qr['payload'] ?? null;
            $order->pix_qr_image = $qr['encodedImage'] ?? null;
            $order->pix_expires_at = isset($qr['expirationDate']) ? Carbon::parse($qr['expirationDate']) : null;
        }

        $order->save();
    }
}
