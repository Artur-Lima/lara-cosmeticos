@props(['product'])
@php
    $range = $product->priceRange();
    $hasRange = $range && $range[0] !== $range[1];
    // Com faixa de preço, o card mostra "a partir de" + a variante mais barata.
    $variant = $hasRange
        ? $product->activeVariants->sortBy(fn ($v) => (float) $v->price)->first()
        : $product->cardVariant();
    $image = $product->images->first();
    $soldOut = $product->isSoldOut();
    $options = $product->optionsLabel();
@endphp
<article class="group relative">
    <a href="{{ route('product.show', $product) }}" class="block focus:outline-none">
        <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-quartz">
            @if ($image)
                <img src="{{ $image->thumb_url }}" alt="{{ $product->name }}" loading="lazy" decoding="async"
                     @class(['h-full w-full object-cover transition duration-500 group-hover:scale-[1.03]', 'opacity-60' => $soldOut])>
            @else
                <div class="grid h-full place-items-center p-6 text-center font-display text-2xl text-brand-300">{{ $product->brand?->name ?? config('shop.name') }}</div>
            @endif

            @if ($soldOut)
                <span class="absolute left-3 top-3 rounded-full bg-white/90 px-3 py-1 text-xs font-medium">Esgotado</span>
            @elseif ($variant?->isOnSale())
                <span class="absolute left-3 top-3 rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white">−{{ $variant->discountPercent() }}%</span>
            @endif
        </div>

        <div class="mt-3 space-y-1">
            @if ($product->brand)<p class="text-xs text-brand-600">{{ $product->brand->name }}</p>@endif
            <h3 class="font-display text-xl leading-tight group-hover:text-brand-700 group-focus-visible:underline">{{ $product->name }}</h3>
            @if ($variant)
                <x-store.price :variant="$variant" :from="$hasRange" />
            @endif
            @if ($options)<p class="text-xs text-brand-700/80">{{ $options }}</p>@endif
        </div>
    </a>
</article>
