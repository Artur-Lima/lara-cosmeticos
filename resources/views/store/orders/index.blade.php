<x-store-layout title="Meus pedidos">
    <div class="mx-auto max-w-3xl px-4 pt-8 sm:px-6">
        <h1 class="font-display text-5xl">Meus pedidos</h1>

        @if ($orders->isEmpty())
            <div class="mt-8 rounded-2xl bg-quartz px-6 py-14 text-center">
                <p class="font-display text-3xl">Nenhum pedido ainda</p>
                <a href="{{ route('catalog') }}" class="mt-6 inline-block rounded-full bg-brand-600 px-7 py-3 font-medium text-white hover:bg-brand-700">Ver produtos</a>
            </div>
        @else
            <ul class="mt-8 divide-y divide-brand-100 border-y border-brand-100">
                @foreach ($orders as $order)
                    <li>
                        <a href="{{ route('orders.show', $order) }}" class="flex items-center justify-between gap-4 py-4 hover:text-brand-700">
                            <div>
                                <p class="font-medium">{{ $order->code }}</p>
                                <p class="text-sm text-brand-700">{{ $order->created_at->format('d/m/Y') }}, {{ $order->items_count }} {{ $order->items_count === 1 ? 'item' : 'itens' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold tabular-nums">{{ \App\Support\Money::brl($order->total) }}</p>
                                <p class="text-sm">{{ $order->status->label() }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>
</x-store-layout>
