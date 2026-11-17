<x-admin-layout title="Produtos">
    <x-slot name="actions">
        <a href="{{ route('admin.products.create') }}" class="inline-flex items-center rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">+ Novo produto</a>
    </x-slot>

    <form method="GET" class="mb-4 flex flex-wrap gap-2 text-sm">
        <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Buscar por nome, SKU ou código de barras"
               class="flex-1 min-w-[14rem] rounded-md border-stone-300 text-sm focus:border-brand-500 focus:ring-brand-500">
        <select name="status" class="rounded-md border-stone-300 text-sm">
            <option value="">Todos os status</option>
            @foreach (\App\Enums\ProductStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <select name="category" class="rounded-md border-stone-300 text-sm">
            <option value="">Todas as categorias</option>
            @foreach ($categories as $id => $name)
                <option value="{{ $id }}" @selected((int) ($filters['category'] ?? 0) === $id)>{{ $name }}</option>
            @endforeach
        </select>
        <select name="brand" class="rounded-md border-stone-300 text-sm">
            <option value="">Todas as marcas</option>
            @foreach ($brands as $id => $name)
                <option value="{{ $id }}" @selected((int) ($filters['brand'] ?? 0) === $id)>{{ $name }}</option>
            @endforeach
        </select>
        <label class="inline-flex items-center gap-2 px-2">
            <input type="checkbox" name="sem_estoque" value="1" @checked($filters['sem_estoque'] ?? false) class="rounded border-stone-300 text-brand-600">
            Esgotados
        </label>
        <button class="rounded-md border border-stone-300 bg-white px-4 py-2 font-medium hover:bg-stone-50">Filtrar</button>
    </form>

    <div class="overflow-x-auto rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
            <tr>
                <th class="px-4 py-3">Produto</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Preço</th>
                <th class="px-4 py-3 text-right">Estoque</th>
                <th class="px-4 py-3"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($products as $product)
                @php($range = $product->priceRange())
                <tr class="hover:bg-stone-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            @if ($img = $product->images->first())
                                <img src="{{ $img->url }}" alt="" class="h-12 w-12 rounded object-cover bg-stone-100" loading="lazy">
                            @else
                                <div class="h-12 w-12 rounded bg-stone-100 grid place-items-center text-[10px] text-stone-400">sem foto</div>
                            @endif
                            <div>
                                <a href="{{ route('admin.products.edit', $product) }}" class="font-medium hover:text-brand-700">{{ $product->name }}</a>
                                <p class="text-xs text-stone-500">
                                    {{ $product->brand?->name ?? 'Sem marca' }} · {{ $product->category?->name ?? 'Sem categoria' }}
                                    · {{ $product->variants_count }} {{ $product->variants_count === 1 ? 'variante' : 'variantes' }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-100 text-emerald-800' => $product->status === \App\Enums\ProductStatus::Active,
                            'bg-stone-100 text-stone-700' => $product->status === \App\Enums\ProductStatus::Draft,
                            'bg-amber-100 text-amber-800' => $product->status === \App\Enums\ProductStatus::Archived,
                        ])>{{ $product->status->label() }}</span>
                        @if ($product->is_featured)<span class="ml-1 text-xs text-brand-700">★ destaque</span>@endif
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap">
                        @if ($range)
                            R$ {{ number_format($range[0], 2, ',', '.') }}@if ($range[0] !== $range[1]) – {{ number_format($range[1], 2, ',', '.') }}@endif
                        @else
                            <span class="text-stone-400">—</span>
                        @endif
                    </td>
                    <td @class(['px-4 py-3 text-right font-medium', 'text-red-600' => (int) $product->stock_total === 0])>{{ (int) $product->stock_total }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.products.edit', $product) }}" class="text-brand-700 hover:underline">Editar</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-stone-500">Nenhum produto encontrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $products->links() }}</div>
</x-admin-layout>
