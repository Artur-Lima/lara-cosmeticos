@php
    $shelf = $featured->filter(fn ($p) => $p->images->isNotEmpty())->take(3)->values();
    $perfumaria = $categories->firstWhere('slug', 'perfumaria');
@endphp

<x-store-layout>
    {{-- Abertura: a promessa da loja + prateleira em arco --}}
    <section class="mx-auto grid max-w-6xl items-center gap-10 px-4 pb-16 pt-10 sm:px-6 lg:grid-cols-[1.1fr_1fr] lg:pt-16">
        <div class="max-w-xl">
            <h1 class="font-display text-[2.6rem] leading-[1.05] text-ink sm:text-6xl">
                Seu perfume favorito, entregue hoje em {{ \App\Models\ShippingZone::CITY }}.
            </h1>
            <p class="mt-5 text-lg leading-relaxed text-brand-800">
                Natura, O Boticário e Eudora com entrega no mesmo dia ou retirada sem custo na loja da Lara.
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ $perfumaria ? route('catalog.category', $perfumaria) : route('catalog') }}"
                   class="rounded-full bg-brand-600 px-7 py-3 font-medium text-white hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                    Ver perfumes
                </a>
                <a href="{{ route('catalog') }}" class="rounded-full border border-brand-300 px-7 py-3 font-medium hover:bg-quartz">Ver todos os produtos</a>
            </div>
        </div>

        @if ($shelf->isNotEmpty())
            <div class="flex items-end justify-center gap-3 sm:gap-5" aria-label="Produtos em destaque">
                @foreach ($shelf as $i => $item)
                    <a href="{{ route('product.show', $item) }}"
                       @class([
                           'group block w-1/3 overflow-hidden rounded-t-full bg-quartz',
                           'aspect-[3/5]' => $i === 1,
                           'aspect-[3/4]' => $i !== 1,
                       ])>
                        <img src="{{ $item->images->first()->thumb_url }}" alt="{{ $item->name }}"
                             class="h-full w-full object-cover transition duration-700 group-hover:scale-105" @if ($i > 0) loading="lazy" @endif>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    @if ($featured->isEmpty() && $newest->isEmpty())
        <section class="mx-auto max-w-6xl px-4 sm:px-6">
            <div class="rounded-2xl bg-quartz px-6 py-16 text-center">
                <p class="font-display text-3xl">A loja está sendo abastecida</p>
                <p class="mt-2 text-brand-800">Os produtos aparecem aqui assim que forem publicados no painel.</p>
            </div>
        </section>
    @endif

    @if ($featured->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 sm:px-6" aria-labelledby="destaques">
            <div class="flex items-end justify-between gap-4">
                <h2 id="destaques" class="font-display text-4xl">Escolhas da Lara</h2>
                <a href="{{ route('catalog') }}" class="shrink-0 text-sm font-medium text-brand-600 underline-offset-4 hover:underline">Ver todos</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-4">
                @foreach ($featured as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    @if ($categories->isNotEmpty())
        <section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6" aria-labelledby="categorias">
            <h2 id="categorias" class="font-display text-4xl">Do que você precisa hoje?</h2>
            <ul class="mt-6 divide-y divide-brand-100 border-y border-brand-100">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('catalog.category', $category) }}" class="group flex items-baseline justify-between gap-4 py-5">
                            <span class="font-display text-3xl transition group-hover:translate-x-1 group-hover:text-brand-600 sm:text-4xl">{{ $category->name }}</span>
                            <span class="text-sm text-brand-700">{{ $category->visible_count }} {{ $category->visible_count === 1 ? 'produto' : 'produtos' }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($newest->isNotEmpty())
        <section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6" aria-labelledby="novidades">
            <div class="flex items-end justify-between gap-4">
                <h2 id="novidades" class="font-display text-4xl">Chegaram agora</h2>
                <a href="{{ route('catalog', ['ordem' => 'novidades']) }}" class="shrink-0 text-sm font-medium text-brand-600 underline-offset-4 hover:underline">Ver novidades</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-4">
                @foreach ($newest as $product)
                    <x-store.product-card :product="$product" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6" aria-label="Como funciona a entrega">
        <div class="grid gap-8 rounded-2xl bg-quartz px-6 py-10 sm:grid-cols-3 sm:px-10">
            <div>
                <p class="font-display text-2xl">Entrega na cidade</p>
                <p class="mt-2 text-sm leading-relaxed text-brand-800">Levamos até você em {{ \App\Models\ShippingZone::CITY }}. A taxa aparece antes de pagar.</p>
            </div>
            <div>
                <p class="font-display text-2xl">Retirada na loja</p>
                <p class="mt-2 text-sm leading-relaxed text-brand-800">Prefere buscar? Escolha retirada e não paga frete.</p>
            </div>
            <div>
                <p class="font-display text-2xl">PIX ou cartão</p>
                <p class="mt-2 text-sm leading-relaxed text-brand-800">Pagamento confirmado na hora e aviso do pedido no seu WhatsApp.</p>
            </div>
        </div>
    </section>
</x-store-layout>
