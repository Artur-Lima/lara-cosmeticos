@php
    use App\Enums\DeliveryMethod;
    use App\Enums\OrderStatus;
    use App\Enums\PaymentMethod;
    use App\Support\Money;
    $awaitingPix = $order->isAwaitingPayment() && $order->payment_method === PaymentMethod::Pix && $order->pix_payload;
@endphp

<x-store-layout :title="'Pedido '.$order->code">
    <div class="mx-auto max-w-3xl px-4 pt-8 sm:px-6"
         @if ($order->isAwaitingPayment())
             x-data="{ async poll() { const r = await fetch('{{ route('orders.status', $order) }}', { headers: { Accept: 'application/json' } }); if (r.ok && (await r.json()).paid) location.reload(); } }"
             x-init="setInterval(() => poll(), 5000)"
         @endif>

        <p class="text-sm text-brand-700">Pedido {{ $order->code }}</p>

        @switch(true)
            @case($awaitingPix)
                <h1 class="font-display text-5xl">Falta só o PIX</h1>
                <p class="mt-2 text-brand-800">Pague {{ Money::brl($order->total) }} com o QR Code ou o código abaixo. Esta página atualiza sozinha quando o pagamento cair.</p>

                <div class="mt-8 grid items-center gap-8 rounded-2xl bg-white p-6 ring-1 ring-brand-100 sm:grid-cols-[14rem_1fr]">
                    @if ($order->pix_qr_image)
                        <img src="data:image/png;base64,{{ $order->pix_qr_image }}" alt="QR Code PIX do pedido {{ $order->code }}" class="mx-auto w-56">
                    @endif
                    <div x-data="{ copied: false }">
                        <label for="pix-code" class="text-sm font-medium">PIX copia e cola</label>
                        <textarea id="pix-code" readonly rows="4" class="mt-1 w-full rounded-xl border-brand-200 bg-mist font-mono text-xs" @focus="$el.select()">{{ $order->pix_payload }}</textarea>
                        <button type="button" class="mt-2 rounded-full bg-ink px-5 py-2.5 text-sm font-medium text-white"
                                @click="navigator.clipboard.writeText(@js($order->pix_payload)); copied = true; setTimeout(() => copied = false, 2500)"
                                x-text="copied ? 'Código copiado' : 'Copiar código'">Copiar código</button>
                        @if ($order->pix_expires_at)
                            <p class="mt-3 text-xs text-brand-700">Válido até {{ $order->pix_expires_at->timezone(config('app.timezone'))->format('d/m/Y \à\s H:i') }}.</p>
                        @endif
                    </div>
                </div>
                @break

            @case($order->isAwaitingPayment())
                <h1 class="font-display text-5xl">Aguardando pagamento</h1>
                <p class="mt-2 text-brand-800">Assim que o Asaas confirmar, esta página atualiza.</p>
                @if ($order->payment_url)
                    <a href="{{ $order->payment_url }}" class="mt-6 inline-block rounded-full bg-brand-600 px-7 py-3 font-medium text-white hover:bg-brand-700">Abrir página de pagamento</a>
                @endif
                @break

            @case($order->status === OrderStatus::Cancelled)
                <h1 class="font-display text-5xl">Pedido cancelado</h1>
                <p class="mt-2 text-brand-800">Se você já pagou, fale com a loja pelo WhatsApp que resolvemos.</p>
                @break

            @default
                <h1 class="font-display text-5xl">{{ $order->status === OrderStatus::Paid ? 'Pagamento confirmado!' : $order->status->label() }}</h1>
                <p class="mt-2 text-brand-800">
                    @if ($order->delivery_method === DeliveryMethod::Pickup)
                        Avisamos no seu WhatsApp quando estiver pronto para retirar em {{ config('shop.address') }}.
                    @else
                        Avisamos no seu WhatsApp quando sair para entrega.
                    @endif
                </p>
        @endswitch

        <section class="mt-10" aria-labelledby="itens">
            <h2 id="itens" class="font-display text-3xl">Itens</h2>
            <ul class="mt-4 divide-y divide-brand-100 border-y border-brand-100 text-sm">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-4 py-3">
                        <span>{{ $item->quantity }}× {{ $item->product_name }} <span class="text-brand-700">({{ $item->variant_label }})</span></span>
                        <span class="tabular-nums">{{ Money::brl($item->total) }}</span>
                    </li>
                @endforeach
            </ul>
            <dl class="mt-4 space-y-1 text-sm">
                <div class="flex justify-between"><dt>Subtotal</dt><dd class="tabular-nums">{{ Money::brl($order->subtotal) }}</dd></div>
                <div class="flex justify-between"><dt>{{ $order->delivery_method->label() }}</dt><dd class="tabular-nums">{{ (float) $order->shipping_fee > 0 ? Money::brl($order->shipping_fee) : 'grátis' }}</dd></div>
                <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd class="tabular-nums">{{ Money::brl($order->total) }}</dd></div>
            </dl>
        </section>

        @if ($order->address)
            <section class="mt-10">
                <h2 class="font-display text-3xl">Entrega</h2>
                <p class="mt-2 text-sm leading-relaxed">
                    {{ $order->address['street'] }}, {{ $order->address['number'] }}@if ($order->address['complement'] ?? null), {{ $order->address['complement'] }}@endif<br>
                    {{ $order->address['neighborhood'] }}, {{ $order->address['city'] }} – {{ $order->address['state'] }}
                    @if ($order->address['reference'] ?? null)<br><span class="text-brand-700">Referência: {{ $order->address['reference'] }}</span>@endif
                </p>
            </section>
        @endif

        <a href="{{ route('orders.index') }}" class="mt-10 inline-block text-sm underline-offset-4 hover:underline">Ver todos os meus pedidos</a>
    </div>
</x-store-layout>
