@php
    use App\Enums\OrderStatus;
    use App\Support\Money;
    use App\Support\Phone;
    $next = $order->status->nextFor($order->delivery_method);
    $phone = $order->customer['phone'] ?? null;
@endphp
<x-admin-layout :title="'Pedido '.$order->code">
    <x-slot name="actions">
        <span class="text-sm text-stone-500">{{ $order->created_at->format('d/m/Y H:i') }}</span>
    </x-slot>

    @if ($order->needs_attention)
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            <p><span class="font-semibold">Precisa de atenção:</span> {{ $order->attention_reason }}</p>
            <form method="POST" action="{{ route('admin.orders.resolve', $order) }}">
                @csrf @method('PATCH')
                <button class="rounded-md border border-red-300 bg-white px-3 py-1.5 font-medium hover:bg-red-100">Marcar como resolvido</button>
            </form>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
        <div class="space-y-6">
            <section class="rounded-lg border border-stone-200 bg-white">
                <h2 class="border-b border-stone-100 px-5 py-3 font-semibold">Itens</h2>
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-stone-100">
                    @foreach ($order->items as $item)
                        <tr>
                            <td class="px-5 py-3">
                                {{ $item->product_name }} <span class="text-stone-500">({{ $item->variant_label }})</span>
                                <span class="block font-mono text-xs text-stone-500">{{ $item->sku }}</span>
                                @if ($order->status->isPaid() && ! $item->stock_deducted)
                                    <span class="text-xs font-medium text-red-700">Não saiu do estoque (faltou no pagamento)</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">{{ $item->quantity }} × {{ Money::brl($item->unit_price) }}</td>
                            <td class="px-5 py-3 text-right font-medium tabular-nums">{{ Money::brl($item->total) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot class="text-sm">
                        <tr><td colspan="2" class="px-5 pt-3 text-right text-stone-500">Subtotal</td><td class="px-5 pt-3 text-right tabular-nums">{{ Money::brl($order->subtotal) }}</td></tr>
                        <tr><td colspan="2" class="px-5 text-right text-stone-500">{{ $order->delivery_method->label() }}</td><td class="px-5 text-right tabular-nums">{{ Money::brl($order->shipping_fee) }}</td></tr>
                        <tr><td colspan="2" class="px-5 pb-3 text-right font-semibold">Total</td><td class="px-5 pb-3 text-right font-semibold tabular-nums">{{ Money::brl($order->total) }}</td></tr>
                    </tfoot>
                </table>
            </section>

            @if ($order->notes)
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm">
                    <h2 class="font-semibold">Recado do cliente</h2>
                    <p class="mt-1 whitespace-pre-line">{{ $order->notes }}</p>
                </section>
            @endif

            <section class="rounded-lg border border-stone-200 bg-white p-5">
                <h2 class="font-semibold">Histórico</h2>
                <ol class="mt-3 space-y-3 text-sm">
                    @foreach ($order->history as $h)
                        <li class="border-l-2 border-brand-200 pl-3">
                            <p class="font-medium">{{ $h->to_status->label() }}</p>
                            <p class="text-xs text-stone-500">{{ $h->created_at->format('d/m/Y H:i') }}, {{ $h->user?->name ?? 'sistema' }}</p>
                            @if ($h->note)<p class="text-stone-700">{{ $h->note }}</p>@endif
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>

        <aside class="space-y-6">
            <section class="rounded-lg border border-stone-200 bg-white p-5">
                <p class="text-sm text-stone-500">Status</p>
                <p class="text-xl font-semibold">{{ $order->status->label() }}</p>
                <p class="mt-1 text-sm text-stone-500">{{ $order->payment_method->label() }}@if ($order->paid_at), pago em {{ $order->paid_at->format('d/m H:i') }}@endif</p>

                @if ($next)
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-4 space-y-2"
                          x-data="{ to: '{{ $next[0]->value }}' }"
                          @submit="if (to === 'cancelado' && !confirm('Cancelar este pedido?{{ $order->status->isPaid() ? ' O estoque volta e o estorno deve ser feito no Asaas.' : '' }}')) $event.preventDefault()">
                        @csrf @method('PATCH')
                        <label for="to" class="text-sm font-medium">Mudar para</label>
                        <select id="to" name="status" x-model="to" class="block w-full rounded-md border-stone-300 text-sm">
                            @foreach ($next as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        <input name="note" maxlength="300" placeholder="Observação (opcional)" class="block w-full rounded-md border-stone-300 text-sm">
                        <button class="w-full rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">Atualizar status</button>
                    </form>
                @endif
            </section>

            <section class="rounded-lg border border-stone-200 bg-white p-5 text-sm">
                <h2 class="font-semibold">Cliente</h2>
                <p class="mt-2">{{ $order->customer['name'] ?? '' }}</p>
                <p class="text-stone-500">{{ $order->customer['email'] ?? '' }}</p>
                @if ($phone)
                    <a href="https://wa.me/55{{ $phone }}?text={{ rawurlencode('Olá! Sobre o seu pedido '.$order->code.' na '.config('shop.name').':') }}" target="_blank" rel="noopener"
                       class="mt-2 inline-block font-medium text-sage-700 hover:underline">WhatsApp {{ Phone::format($phone) }}</a>
                @endif
            </section>

            <section class="rounded-lg border border-stone-200 bg-white p-5 text-sm">
                <h2 class="font-semibold">{{ $order->delivery_method->label() }}</h2>
                @if ($order->address)
                    <p class="mt-2 leading-relaxed">
                        {{ $order->address['street'] }}, {{ $order->address['number'] }}@if ($order->address['complement'] ?? null), {{ $order->address['complement'] }}@endif<br>
                        {{ $order->address['neighborhood'] }}
                        @if ($order->address['reference'] ?? null)<br><span class="text-stone-500">Ref.: {{ $order->address['reference'] }}</span>@endif
                    </p>
                @else
                    <p class="mt-2 text-stone-500">Cliente retira na loja.</p>
                @endif
            </section>

            @if ($order->asaas_payment_id)
                <p class="text-xs text-stone-500">Cobrança Asaas: <span class="font-mono">{{ $order->asaas_payment_id }}</span></p>
            @endif
        </aside>
    </div>
</x-admin-layout>
