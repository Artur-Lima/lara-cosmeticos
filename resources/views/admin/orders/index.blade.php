@php use App\Enums\OrderStatus; use App\Support\Money; @endphp
<x-admin-layout title="Pedidos">
    <form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Código ou nome do cliente"
               class="flex-1 min-w-[12rem] rounded-md border-stone-300 text-sm focus:border-brand-500 focus:ring-brand-500">
        <select name="status" class="rounded-md border-stone-300 text-sm">
            <option value="">Todos os status</option>
            @foreach (OrderStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <label class="inline-flex items-center gap-2 px-2">
            <input type="checkbox" name="atencao" value="1" @checked($filters['atencao'] ?? false) class="rounded border-stone-300 text-brand-600">
            Só com alerta
        </label>
        <button class="rounded-md border border-stone-300 bg-white px-4 py-2 font-medium hover:bg-stone-50">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
            <tr><th class="px-4 py-3">Pedido</th><th class="px-4 py-3">Cliente</th><th class="px-4 py-3">Recebimento</th><th class="px-4 py-3">Status</th><th class="px-4 py-3 text-right">Total</th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($orders as $order)
                <tr class="hover:bg-stone-50">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.orders.show', $order) }}" class="font-medium hover:text-brand-700">{{ $order->code }}</a>
                        @if ($order->needs_attention)<span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">alerta</span>@endif
                        <p class="text-xs text-stone-500">{{ $order->created_at->format('d/m H:i') }}, {{ $order->items_count }} {{ $order->items_count === 1 ? 'item' : 'itens' }}</p>
                    </td>
                    <td class="px-4 py-3">{{ $order->customer['name'] ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $order->delivery_method->label() }}@if ($order->address)<span class="block text-xs text-stone-500">{{ $order->address['neighborhood'] }}</span>@endif</td>
                    <td class="px-4 py-3">
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-stone-100 text-stone-700' => $order->status === OrderStatus::AwaitingPayment,
                            'bg-brand-100 text-brand-800' => in_array($order->status, [OrderStatus::Paid, OrderStatus::Preparing], true),
                            'bg-sky-100 text-sky-800' => in_array($order->status, [OrderStatus::ReadyForPickup, OrderStatus::OutForDelivery], true),
                            'bg-emerald-100 text-emerald-800' => $order->status === OrderStatus::Delivered,
                            'bg-red-50 text-red-700' => $order->status === OrderStatus::Cancelled,
                        ])>{{ $order->status->label() }}</span>
                    </td>
                    <td class="px-4 py-3 text-right font-medium tabular-nums">{{ Money::brl($order->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Nenhum pedido encontrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $orders->links() }}</div>
</x-admin-layout>
