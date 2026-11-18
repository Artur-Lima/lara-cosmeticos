@php
    $search = $filters['busca'] ?? null;
    $title = match (true) {
        (bool) $category => $category->name,
        (bool) $brand && ! $category => $brand->name,
        filled($search) => "Resultados para “{$search}”",
        default => 'Todos os produtos',
    };
    $baseRoute = $category ? route('catalog.category', $category) : ($brand && request()->routeIs('catalog.brand') ? route('catalog.brand', $brand) : route('catalog'));
    $select = 'rounded-full border-brand-200 bg-white py-2 pl-4 pr-9 text-sm focus:border-brand-500 focus:ring-brand-500';
@endphp

<x-store-layout :title="$title" :description="$category?->description">
    <div class="mx-auto max-w-6xl px-4 pt-8 sm:px-6">
        @if ($parentCategory)
            <nav aria-label="Caminho" class="text-sm text-brand-700">
                <a href="{{ route('catalog.category', $parentCategory) }}" class="hover:underline">{{ $parentCategory->name }}</a>
                <span aria-hidden="true" class="mx-1">/</span>
                <span>{{ $category->name }}</span>
            </nav>
        @endif

        <h1 class="mt-1 font-display text-5xl leading-tight">{{ $title }}</h1>
        <p class="mt-1 text-sm text-brand-700">{{ $products->total() }} {{ $products->total() === 1 ? 'produto' : 'produtos' }}</p>

        @if ($subcategories->isNotEmpty())
            <ul class="mt-6 flex flex-wrap gap-2" aria-label="Subcategorias">
                @foreach ($subcategories as $sub)
                    <li><a href="{{ route('catalog.category', $sub) }}" class="block rounded-full border border-brand-200 bg-white px-4 py-2 text-sm hover:border-brand-400">{{ $sub->name }}</a></li>
                @endforeach
            </ul>
        @endif

        <form method="GET" action="{{ $baseRoute }}" x-data @change="$el.requestSubmit()"
              class="mt-6 flex flex-wrap items-center gap-2 border-y border-brand-100 py-3">
            @if (filled($search))<input type="hidden" name="busca" value="{{ $search }}">@endif

            <label class="sr-only" for="f-publico">Público</label>
            <select id="f-publico" name="publico" class="{{ $select }}">
                <option value="">Para todos</option>
                <option value="feminino" @selected(($filters['publico'] ?? '') === 'feminino')>Feminino</option>
                <option value="masculino" @selected(($filters['publico'] ?? '') === 'masculino')>Masculino</option>
            </select>

            @unless (request()->routeIs('catalog.brand'))
                <label class="sr-only" for="f-marca">Marca</label>
                <select id="f-marca" name="marca" class="{{ $select }}">
                    <option value="">Todas as marcas</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b->slug }}" @selected($brand?->id === $b->id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            @endunless

            <label class="ml-auto text-sm text-brand-700" for="f-ordem">Ordenar</label>
            <select id="f-ordem" name="ordem" class="{{ $select }}">
                @foreach (\App\Http\Controllers\Store\CatalogController::SORTS as $value => $label)
                    <option value="{{ $value }}" @selected($sort === $value)>{{ $label }}</option>
                @endforeach
            </select>

            <noscript><button class="rounded-full bg-ink px-4 py-2 text-sm text-white">Aplicar</button></noscript>
        </form>

        @if ($products->isEmpty())
            <div class="py-20 text-center">
                <p class="font-display text-3xl">Nenhum produto encontrado</p>
                <p class="mt-2 text-brand-800">Tente outra palavra ou tire algum filtro.</p>
                <a href="{{ $category ? route('catalog.category', $category) : route('catalog') }}" class="mt-6 inline-block rounded-full bg-ink px-6 py-3 text-sm font-medium text-white">Limpar filtros</a>
            </div>
        @else
            <div class="mt-8 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>

            <div class="mt-12">{{ $products->onEachSide(1)->links() }}</div>
        @endif
    </div>
</x-store-layout>
