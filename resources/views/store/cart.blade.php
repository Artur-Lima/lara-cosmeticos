@php use App\Support\Money; $maxQty = (int) config('shop.max_qty_per_item', 10); @endphp

<x-store-layout title="Sacola">
    <div class="mx-auto max-w-6xl px-4 pt-8 sm:px-6">
        <h1 class="font-display text-5xl">Sacola</h1>

        @foreach ($notices as $notice)
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">{{ $notice }}</p>
        @endforeach

        @if ($lines->isEmpty())
            <div class="mt-10 rounded-2xl bg-quartz px-6 py-16 text-center">
                <p class="font-display text-3xl">Sua sacola está vazia</p>
                <p class="mt-2 text-brand-800">Escolha seus produtos e eles aparecem aqui.</p>
                <a href="{{ route('catalog') }}" class="mt-6 inline-block rounded-full bg-brand-600 px-7 py-3 font-medium text-white hover:bg-brand-700">Ver produtos</a>
            </div>
        @else
            <div class="mt-8 grid gap-10 lg:grid-cols-[1fr_22rem]">
                <ul class="divide-y divide-brand-100 border-y border-brand-100">
                    @foreach ($lines as $line)
                        @php($variant = $line->variant)
                        @php($product = $line->product())
                        <li class="flex gap-4 py-5">
                            <a href="{{ route('product.show', [$product, 'variante' => $variant->id]) }}" class="h-28 w-24 shrink-0 overflow-hidden rounded-xl bg-quartz">
                                @if ($img = $product->images->first())
                                    <img src="{{ $img->thumb_url }}" alt="{{ $product->name }}" class="h-full w-full object-cover" loading="lazy">
                                @endif
                            </a>
                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex justify-between gap-4">
                                    <div class="min-w-0">
                                        <a href="{{ route('product.show', [$product, 'variante' => $variant->id]) }}" class="font-display text-xl leading-tight hover:text-brand-700">{{ $product->name }}</a>
                                        <p class="text-sm text-brand-700">{{ $variant->label }}</p>
                                    </div>
                                    <p class="shrink-0 font-semibold tabular-nums">{{ Money::brl($line->total()) }}</p>
                                </div>

                                <div class="mt-auto flex items-center gap-4 pt-3">
                                    <form method="POST" action="{{ route('cart.update', $variant) }}" x-data @change="$el.requestSubmit()">
                                        @csrf @method('PATCH')
                                        <label for="qtd-{{ $variant->id }}" class="sr-only">Quantidade de {{ $product->name }}</label>
                                        <select id="qtd-{{ $variant->id }}" name="quantity" class="rounded-full border-brand-200 bg-white py-1.5 pl-4 pr-9 text-sm focus:border-brand-500 focus:ring-brand-500">
                                            @foreach (range(1, max(1, min($variant->stock, $maxQty))) as $n)
                                                <option value="{{ $n }}" @selected($n === $line->quantity)>{{ $n }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button class="text-sm underline">Atualizar</button></noscript>
                                    </form>
                                    <span class="text-sm text-brand-700 tabular-nums">{{ Money::brl($line->unitPrice()) }} cada</span>
                                    <form method="POST" action="{{ route('cart.destroy', $variant->id) }}" class="ml-auto">
                                        @csrf @method('DELETE')
                                        <button class="text-sm text-brand-700 underline-offset-4 hover:text-red-700 hover:underline">Remover</button>
                                    </form>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <aside class="h-fit rounded-2xl bg-quartz p-6 lg:sticky lg:top-28" aria-label="Resumo">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt>Subtotal</dt>
                            <dd class="font-semibold tabular-nums">{{ Money::brl($subtotal) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 text-brand-800">
                            <dt>Entrega</dt>
                            <dd class="text-right">Calculada no próximo passo. Retirada na loja é grátis.</dd>
                        </div>
                    </dl>
                    {{-- Checkout com Asaas entra no Sprint 3 --}}
                    <button type="button" disabled class="mt-6 w-full cursor-not-allowed rounded-full bg-brand-600/60 px-6 py-3 font-medium text-white">Finalizar compra</button>
                    <p class="mt-2 text-center text-xs text-brand-700">Pagamento online em breve.</p>
                    <a href="{{ route('catalog') }}" class="mt-4 block text-center text-sm underline-offset-4 hover:underline">Continuar comprando</a>
                </aside>
            </div>
        @endif
    </div>
</x-store-layout>
