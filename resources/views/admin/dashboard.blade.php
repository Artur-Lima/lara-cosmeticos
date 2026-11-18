<x-admin-layout title="Painel">
    @unless ($hasShippingZones)
        <div class="mb-6 rounded-md bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-900">
            Nenhuma região de entrega cadastrada: os clientes só vão conseguir escolher retirada na loja.
            <a href="{{ route('admin.shipping-zones.create') }}" class="font-medium underline">Cadastrar taxa de entrega</a>
        </div>
    @endunless

    @if ($attention->isNotEmpty())
        <div class="mb-6 rounded-md border border-red-200 bg-red-50 p-4 text-sm text-red-900">
            <p class="font-semibold">Pedidos que precisam de atenção</p>
            <ul class="mt-2 space-y-1">
                @foreach ($attention as $o)
                    <li><a href="{{ route('admin.orders.show', $o) }}" class="font-medium underline">{{ $o->code }}</a> — {{ $o->attention_reason }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-4 grid gap-4 sm:grid-cols-2">
        <a href="{{ route('admin.orders.index') }}" class="rounded-lg bg-white border border-stone-200 p-5 hover:border-brand-300">
            <p class="text-sm text-stone-500">Pedidos para separar ou entregar</p>
            <p @class(['mt-1 text-3xl font-semibold', 'text-brand-700' => $toHandle > 0])>{{ $toHandle }}</p>
        </a>
        <div class="rounded-lg bg-white border border-stone-200 p-5">
            <p class="text-sm text-stone-500">Vendido hoje</p>
            <p class="mt-1 text-3xl font-semibold">{{ \App\Support\Money::brl($paidToday) }}</p>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <a href="{{ route('admin.products.index', ['status' => 'active']) }}" class="rounded-lg bg-white border border-stone-200 p-5 hover:border-brand-300">
            <p class="text-sm text-stone-500">Produtos publicados</p>
            <p class="mt-1 text-3xl font-semibold">{{ $published }}</p>
        </a>
        <a href="{{ route('admin.products.index', ['status' => 'draft']) }}" class="rounded-lg bg-white border border-stone-200 p-5 hover:border-brand-300">
            <p class="text-sm text-stone-500">Rascunhos</p>
            <p class="mt-1 text-3xl font-semibold">{{ $drafts }}</p>
        </a>
        <a href="#repor" class="rounded-lg bg-white border border-stone-200 p-5 hover:border-brand-300">
            <p class="text-sm text-stone-500">Estoque baixo (≤ {{ \App\Http\Controllers\Admin\DashboardController::LOW_STOCK }})</p>
            <p @class(['mt-1 text-3xl font-semibold', 'text-red-600' => $lowStock->isNotEmpty()])>{{ $lowStock->count() }}</p>
        </a>
    </div>

    <section id="repor" class="mt-8">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold">Repor estoque</h2>
            <a href="{{ route('admin.products.create') }}" class="text-sm font-medium text-brand-700 hover:underline">+ Novo produto</a>
        </div>

        @if ($lowStock->isEmpty())
            <p class="mt-3 text-sm text-stone-500">Nenhuma variante publicada com estoque baixo.</p>
        @else
            <div class="mt-3 overflow-hidden rounded-lg border border-stone-200 bg-white">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-stone-100">
                    @foreach ($lowStock as $variant)
                        <tr>
                            <td class="px-4 py-2">
                                <a href="{{ route('admin.products.edit', $variant->product_id) }}" class="font-medium hover:text-brand-700">{{ $variant->product->name }}</a>
                                <span class="text-stone-500">· {{ $variant->label }}</span>
                            </td>
                            <td class="px-4 py-2 text-stone-500 font-mono text-xs">{{ $variant->sku }}</td>
                            <td class="px-4 py-2 text-right">
                                <span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-red-100 text-red-700' => $variant->stock === 0, 'bg-amber-100 text-amber-800' => $variant->stock > 0])>
                                    {{ $variant->stock === 0 ? 'Esgotado' : $variant->stock.' un.' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-admin-layout>
