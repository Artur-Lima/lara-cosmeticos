@props(['variant', 'size' => 'base', 'from' => false])
@php($money = fn ($v) => \App\Support\Money::brl($v))
<p {{ $attributes->class(['flex flex-wrap items-baseline gap-x-2']) }}>
    @if ($variant->isOnSale())
        <span class="text-sm text-brand-400 line-through" aria-label="Preço anterior">{{ $money($variant->compare_at_price) }}</span>
    @endif
    <span @class([
        'font-semibold tabular-nums',
        'text-base' => $size === 'base',
        'text-3xl' => $size === 'lg',
        'text-brand-600' => $variant->isOnSale(),
    ])>@if ($from)<span class="text-sm font-normal text-brand-700">a partir de </span>@endif{{ $money($variant->price) }}</span>
</p>
