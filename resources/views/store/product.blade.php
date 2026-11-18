@php
    use App\Support\Money;
    $maxQty = (int) config('shop.max_qty_per_item', 10);
    $images = $product->images;
    $variantData = $variants->map(fn ($v) => [
        'id' => $v->id,
        'label' => $v->label,
        'price' => Money::brl($v->price),
        'compare' => $v->isOnSale() ? Money::brl($v->compare_at_price) : null,
        'discount' => $v->discountPercent(),
        'stock' => $v->stock,
        'max' => min($v->stock, $maxQty),
        'color' => $v->color_hex,
        'image' => $images->search(fn ($img) => $img->product_variant_id === $v->id),
    ])->values();
    $isSwatch = $variants->contains(fn ($v) => $v->color_hex);
    $whatsapp = config('shop.whatsapp');
    $waText = rawurlencode("Olá! Tenho uma dúvida sobre {$product->name}: ".route('product.show', $product));
    $description = $product->seo_description ?: ($product->short_description ?: str($product->description)->limit(155)->value());
@endphp

<x-store-layout :title="$product->seo_title ?: $product->name" :description="$description" :image="$images->first()?->url">
    <x-slot name="head">
        <script type="application/ld+json">
            {!! json_encode([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $description,
                'image' => $images->pluck('url')->all(),
                'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null,
                'sku' => $selected?->sku,
                'offers' => $variants->map(fn ($v) => [
                    '@type' => 'Offer',
                    'sku' => $v->sku,
                    'price' => number_format((float) $v->price, 2, '.', ''),
                    'priceCurrency' => 'BRL',
                    'availability' => $v->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'url' => route('product.show', $product).'?variante='.$v->id,
                ])->values()->all(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
        </script>
    </x-slot>

    @if ($isPreview)
        <p class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900">
            Prévia: este produto não aparece na loja ({{ $product->status->label() }}{{ $variants->isEmpty() ? ', sem variação ativa' : '' }}).
            <a href="{{ route('admin.products.edit', $product) }}" class="font-medium underline">Editar no painel</a>
        </p>
    @endif

    <div class="mx-auto max-w-6xl px-4 pt-6 sm:px-6"
         x-data="{
            variants: @js($variantData),
            selectedId: @js($selected?->id),
            image: 0,
            qty: 1,
            get v() { return this.variants.find(x => x.id === this.selectedId) ?? this.variants[0] ?? null },
            pick(id) {
                this.selectedId = id;
                this.qty = Math.min(this.qty, Math.max(1, this.v.max));
                if (this.v.image !== false && this.v.image !== null) this.image = this.v.image;
                history.replaceState(null, '', '?variante=' + id);
            },
         }"
         x-init="if (v && v.image !== false && v.image !== null) image = v.image">

        <nav aria-label="Caminho" class="text-sm text-brand-700">
            <a href="{{ route('catalog') }}" class="hover:underline">Produtos</a>
            @if ($product->category?->parent)
                <span aria-hidden="true" class="mx-1">/</span><a href="{{ route('catalog.category', $product->category->parent) }}" class="hover:underline">{{ $product->category->parent->name }}</a>
            @endif
            @if ($product->category)
                <span aria-hidden="true" class="mx-1">/</span><a href="{{ route('catalog.category', $product->category) }}" class="hover:underline">{{ $product->category->name }}</a>
            @endif
        </nav>

        <div class="mt-4 grid gap-8 lg:grid-cols-[1.15fr_1fr] lg:gap-14">
            {{-- Galeria --}}
            <div class="lg:sticky lg:top-28 lg:self-start">
                <div class="aspect-square overflow-hidden rounded-2xl bg-quartz lg:aspect-[4/5]">
                    @forelse ($images as $i => $img)
                        <img src="{{ $img->url }}" alt="{{ $img->alt ?: $product->name }}" x-show="image === {{ $i }}" @if ($i > 0) x-cloak loading="lazy" @endif
                             class="h-full w-full object-cover">
                    @empty
                        <div class="grid h-full place-items-center font-display text-3xl text-brand-300">{{ $product->brand?->name ?? config('shop.name') }}</div>
                    @endforelse
                </div>
                @if ($images->count() > 1)
                    <div class="mt-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($images as $i => $img)
                            <button type="button" @click="image = {{ $i }}" :aria-current="image === {{ $i }}"
                                    :class="image === {{ $i }} ? 'ring-2 ring-brand-600' : 'opacity-70 hover:opacity-100'"
                                    class="h-20 w-16 shrink-0 overflow-hidden rounded-lg bg-quartz" aria-label="Ver foto {{ $i + 1 }}">
                                <img src="{{ $img->thumb_url }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Compra --}}
            <div>
                @if ($product->brand)
                    <a href="{{ route('catalog.brand', $product->brand) }}" class="text-sm text-brand-600 hover:underline">{{ $product->brand->name }}</a>
                @endif
                <h1 class="mt-1 font-display text-4xl leading-tight sm:text-5xl">{{ $product->name }}</h1>
                @if ($product->short_description)
                    <p class="mt-3 text-lg leading-relaxed text-brand-800">{{ $product->short_description }}</p>
                @endif

                <template x-if="v">
                    <div class="mt-6 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span x-show="v.compare" class="text-brand-400 line-through" x-text="v.compare"></span>
                        <span class="text-3xl font-semibold tabular-nums" :class="v.compare && 'text-brand-600'" x-text="v.price"></span>
                        <span x-show="v.discount" class="rounded-full bg-brand-600 px-2.5 py-0.5 text-xs font-semibold text-white" x-text="'−' + v.discount + '%'"></span>
                    </div>
                </template>

                @if ($variants->count() > 1)
                    <fieldset class="mt-6">
                        <legend class="text-sm font-medium">
                            {{ $isSwatch ? 'Cor' : 'Tamanho' }}: <span class="font-normal text-brand-800" x-text="v?.label"></span>
                        </legend>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($variants as $variant)
                                @if ($isSwatch && $variant->color_hex)
                                    <button type="button" @click="pick({{ $variant->id }})" title="{{ $variant->label }}" aria-label="{{ $variant->label }}"
                                            :aria-pressed="selectedId === {{ $variant->id }}"
                                            :class="selectedId === {{ $variant->id }} ? 'ring-2 ring-offset-2 ring-ink' : 'ring-1 ring-brand-200'"
                                            @class(['relative h-10 w-10 rounded-full', 'opacity-40' => $variant->stock < 1])
                                            style="background-color: {{ $variant->color_hex }}"></button>
                                @else
                                    <button type="button" @click="pick({{ $variant->id }})" :aria-pressed="selectedId === {{ $variant->id }}"
                                            :class="selectedId === {{ $variant->id }} ? 'border-ink bg-ink text-white' : 'border-brand-200 bg-white hover:border-brand-400'"
                                            @class(['rounded-full border px-5 py-2 text-sm', 'line-through decoration-brand-300' => $variant->stock < 1])>{{ $variant->label }}</button>
                                @endif
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <form method="POST" action="{{ route('cart.store') }}" class="mt-8" id="comprar">
                    @csrf
                    <input type="hidden" name="variant_id" :value="v?.id">

                    <template x-if="v && v.stock > 0">
                        <div>
                            <p class="text-sm text-sage-700" x-text="v.stock <= 3 ? (v.stock === 1 ? 'Última unidade' : 'Últimas ' + v.stock + ' unidades') : 'Em estoque'"></p>
                            <div class="mt-3 flex gap-3">
                                <div class="flex items-center rounded-full border border-brand-200 bg-white">
                                    <button type="button" @click="qty = Math.max(1, qty - 1)" class="h-12 w-11 text-lg" aria-label="Diminuir quantidade">−</button>
                                    <input type="number" name="quantity" x-model.number="qty" min="1" :max="v.max" readonly
                                           class="w-10 border-0 bg-transparent p-0 text-center tabular-nums focus:ring-0" aria-label="Quantidade">
                                    <button type="button" @click="qty = Math.min(v.max, qty + 1)" class="h-12 w-11 text-lg" aria-label="Aumentar quantidade">+</button>
                                </div>
                                <button class="flex-1 rounded-full bg-brand-600 px-6 py-3 font-medium text-white hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600">
                                    Adicionar à sacola
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-if="!v || v.stock < 1">
                        <div class="rounded-2xl bg-quartz p-5">
                            <p class="font-medium">Esgotado no momento</p>
                            @if ($whatsapp)
                                <p class="mt-1 text-sm text-brand-800">Chame a Lara no WhatsApp para saber quando volta.</p>
                            @endif
                        </div>
                    </template>
                </form>

                @if ($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}?text={{ $waText }}" target="_blank" rel="noopener"
                       class="mt-4 inline-flex items-center gap-2 text-sm font-medium text-sage-700 hover:underline">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.2-.4.2-.4.7-1.3.1-.2 0-.3 0-.5l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-1 2.2 5.2 5.2 0 0 0 1.1 2.8 11.9 11.9 0 0 0 4.6 4c1.7.7 2.4.8 3.2.7.5-.1 1.5-.6 1.7-1.2.2-.6.2-1.1.2-1.2-.1-.1-.3-.2-.5-.3Z"/></svg>
                        Tirar dúvida pelo WhatsApp
                    </a>
                @endif

                <div class="mt-10 divide-y divide-brand-100 border-y border-brand-100">
                    @foreach (['Descrição' => $product->description, 'Modo de uso' => $product->how_to_use, 'Composição' => $product->composition] as $label => $text)
                        @if (filled($text))
                            <details class="group py-4" @if ($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-center justify-between font-display text-2xl">
                                    {{ $label }}
                                    <span class="text-base transition group-open:rotate-45" aria-hidden="true">+</span>
                                </summary>
                                <div class="mt-3 whitespace-pre-line leading-relaxed text-brand-900/90">{{ $text }}</div>
                            </details>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Barra fixa de compra no celular --}}
        <div x-show="v && v.stock > 0" x-cloak class="fixed inset-x-0 bottom-0 z-20 flex items-center gap-3 border-t border-brand-100 bg-mist/95 px-4 py-3 backdrop-blur lg:hidden">
            <div class="min-w-0 flex-1">
                <p class="truncate text-xs text-brand-700" x-text="v?.label"></p>
                <p class="font-semibold tabular-nums" x-text="v?.price"></p>
            </div>
            <button type="submit" form="comprar" class="rounded-full bg-brand-600 px-6 py-3 text-sm font-medium text-white">Adicionar à sacola</button>
        </div>
        <div class="h-16 lg:hidden" aria-hidden="true"></div>{{-- espaço para a barra fixa não cobrir o rodapé --}}
    </div>

    @if ($related->isNotEmpty())
        <section class="mx-auto mt-20 max-w-6xl px-4 sm:px-6" aria-labelledby="relacionados">
            <h2 id="relacionados" class="font-display text-4xl">Combina com você</h2>
            <div class="mt-6 grid grid-cols-2 gap-x-4 gap-y-10 md:grid-cols-4">
                @foreach ($related as $item)
                    <x-store.product-card :product="$item" />
                @endforeach
            </div>
        </section>
    @endif
</x-store-layout>
