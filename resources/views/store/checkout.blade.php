@php
    use App\Support\Money;
    $input = 'mt-1 block w-full rounded-xl border-brand-200 bg-white focus:border-brand-500 focus:ring-brand-500';
    $zoneData = $zones->map(fn ($z) => ['id' => $z->id, 'fee' => (float) $z->fee, 'free_above' => $z->free_above ? (float) $z->free_above : null])->values();
    $hasDelivery = $zones->isNotEmpty();
    $defaultDelivery = old('delivery_method', $hasDelivery && $lastAddress ? 'entrega' : 'retirada');
    $defaultZoneId = (int) old('shipping_zone_id', $zones->firstWhere('name', $lastAddress['neighborhood'] ?? null)?->id) ?: null;
@endphp

<x-store-layout title="Finalizar compra">
    <div class="mx-auto max-w-6xl px-4 pt-8 sm:px-6"
         x-data="{
            delivery: @js($defaultDelivery),
            zoneId: @js($defaultZoneId),
            payment: @js(old('payment_method', 'pix')),
            subtotal: {{ $subtotal }},
            zones: @js($zoneData),
            get fee() {
                if (this.delivery !== 'entrega') return 0;
                const z = this.zones.find(z => z.id === Number(this.zoneId));
                if (!z) return null;
                return (z.free_above !== null && this.subtotal >= z.free_above) ? 0 : z.fee;
            },
            brl(v) { return v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) },
         }">
        <h1 class="font-display text-5xl">Finalizar compra</h1>

        @foreach ($notices as $notice)
            <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">{{ $notice }} Confira o resumo antes de pagar.</p>
        @endforeach

        <form method="POST" action="{{ route('checkout.store') }}" class="mt-8 grid gap-10 lg:grid-cols-[1fr_22rem]">
            @csrf
            <input type="hidden" name="expected_subtotal" value="{{ number_format($subtotal, 2, '.', '') }}">
            <div class="space-y-10">
                {{-- Recebimento --}}
                <fieldset>
                    <legend class="font-display text-3xl">Como você quer receber?</legend>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @if ($hasDelivery)
                            <label class="cursor-pointer rounded-2xl border p-4" :class="delivery === 'entrega' ? 'border-ink bg-white ring-1 ring-ink' : 'border-brand-200'">
                                <input type="radio" name="delivery_method" value="entrega" x-model="delivery" class="sr-only">
                                <span class="block font-medium">Entrega em {{ \App\Models\ShippingZone::CITY }}</span>
                                <span class="mt-1 block text-sm text-brand-700">Levamos no seu endereço</span>
                            </label>
                        @endif
                        <label class="cursor-pointer rounded-2xl border p-4" :class="delivery === 'retirada' ? 'border-ink bg-white ring-1 ring-ink' : 'border-brand-200'">
                            <input type="radio" name="delivery_method" value="retirada" x-model="delivery" class="sr-only">
                            <span class="block font-medium">Retirar na loja</span>
                            <span class="mt-1 block text-sm text-brand-700">Grátis, em {{ config('shop.address') }}</span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('delivery_method')" class="mt-2" />

                    <div x-show="delivery === 'entrega'" x-cloak class="mt-6 grid gap-4 sm:grid-cols-6">
                        <div class="sm:col-span-6">
                            <label for="shipping_zone_id" class="text-sm font-medium">Bairro</label>
                            <select id="shipping_zone_id" name="shipping_zone_id" x-model="zoneId" class="{{ $input }}" :required="delivery === 'entrega'">
                                <option value="">Escolha o bairro</option>
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}">
                                        {{ $zone->name }} ({{ (float) $zone->fee > 0 ? Money::brl($zone->fee) : 'grátis' }}@if ($zone->estimate), {{ $zone->estimate }}@endif)
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('shipping_zone_id')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-4">
                            <label for="street" class="text-sm font-medium">Rua</label>
                            <input id="street" name="street" value="{{ old('street', $lastAddress['street'] ?? '') }}" maxlength="150" autocomplete="address-line1" class="{{ $input }}" :required="delivery === 'entrega'">
                            <x-input-error :messages="$errors->get('street')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <label for="number" class="text-sm font-medium">Número</label>
                            <input id="number" name="number" value="{{ old('number', $lastAddress['number'] ?? '') }}" maxlength="20" class="{{ $input }}" :required="delivery === 'entrega'">
                            <x-input-error :messages="$errors->get('number')" class="mt-1" />
                        </div>
                        <div class="sm:col-span-3">
                            <label for="complement" class="text-sm font-medium">Complemento <span class="font-normal text-brand-600">(opcional)</span></label>
                            <input id="complement" name="complement" value="{{ old('complement', $lastAddress['complement'] ?? '') }}" maxlength="80" autocomplete="address-line2" class="{{ $input }}">
                        </div>
                        <div class="sm:col-span-3">
                            <label for="reference" class="text-sm font-medium">Ponto de referência <span class="font-normal text-brand-600">(opcional)</span></label>
                            <input id="reference" name="reference" value="{{ old('reference', $lastAddress['reference'] ?? '') }}" maxlength="150" class="{{ $input }}" placeholder="Perto da praça, casa azul…">
                        </div>
                    </div>
                </fieldset>

                @if ($needsCpf)
                    <div>
                        <label for="cpf" class="font-display text-3xl">CPF</label>
                        <p class="text-sm text-brand-700">Necessário para emitir a cobrança. Pedimos só na primeira compra.</p>
                        <input id="cpf" name="cpf" value="{{ old('cpf') }}" inputmode="numeric" maxlength="14" required placeholder="000.000.000-00" class="{{ $input }} max-w-xs">
                        <x-input-error :messages="$errors->get('cpf')" class="mt-1" />
                    </div>
                @endif

                {{-- Pagamento --}}
                <fieldset>
                    <legend class="font-display text-3xl">Pagamento</legend>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <label class="cursor-pointer rounded-2xl border p-4" :class="payment === 'pix' ? 'border-ink bg-white ring-1 ring-ink' : 'border-brand-200'">
                            <input type="radio" name="payment_method" value="pix" x-model="payment" class="sr-only">
                            <span class="block font-medium">PIX</span>
                            <span class="mt-1 block text-sm text-brand-700">Aprovação na hora, com QR Code ou copia e cola</span>
                        </label>
                        <label class="cursor-pointer rounded-2xl border p-4" :class="payment === 'cartao' ? 'border-ink bg-white ring-1 ring-ink' : 'border-brand-200'">
                            <input type="radio" name="payment_method" value="cartao" x-model="payment" class="sr-only">
                            <span class="block font-medium">Cartão de crédito</span>
                            <span class="mt-1 block text-sm text-brand-700">Você digita o cartão na página segura do Asaas</span>
                        </label>
                    </div>
                    <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                </fieldset>

                <div>
                    <label for="notes" class="text-sm font-medium">Algum recado para a Lara? <span class="font-normal text-brand-600">(opcional)</span></label>
                    <textarea id="notes" name="notes" rows="2" maxlength="500" class="{{ $input }}" placeholder="É presente, embrulhar por favor…">{{ old('notes') }}</textarea>
                </div>
            </div>

            {{-- Resumo --}}
            <aside class="h-fit rounded-2xl bg-quartz p-6 lg:sticky lg:top-28" aria-label="Resumo do pedido">
                <ul class="space-y-3 text-sm">
                    @foreach ($lines as $line)
                        <li class="flex justify-between gap-3">
                            <span class="min-w-0">{{ $line->quantity }}× {{ $line->product()->name }} <span class="text-brand-700">({{ $line->variant->label }})</span></span>
                            <span class="shrink-0 tabular-nums">{{ Money::brl($line->total()) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-5 space-y-2 border-t border-brand-200 pt-4 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd class="tabular-nums">{{ Money::brl($subtotal) }}</dd></div>
                    <div class="flex justify-between">
                        <dt>Entrega</dt>
                        <dd class="tabular-nums" x-text="fee === null ? 'escolha o bairro' : (fee === 0 ? 'grátis' : brl(fee))"></dd>
                    </div>
                    <div class="flex justify-between border-t border-brand-200 pt-3 text-base font-semibold">
                        <dt>Total</dt>
                        <dd class="tabular-nums" x-text="brl(subtotal + (fee ?? 0))">{{ Money::brl($subtotal) }}</dd>
                    </div>
                </dl>
                <button class="mt-6 w-full rounded-full bg-brand-600 px-6 py-3 font-medium text-white hover:bg-brand-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600"
                        x-text="payment === 'pix' ? 'Gerar PIX' : 'Ir para o pagamento com cartão'">Finalizar compra</button>
                <a href="{{ route('cart.index') }}" class="mt-4 block text-center text-sm underline-offset-4 hover:underline">Voltar para a sacola</a>
            </aside>
        </form>
    </div>
</x-store-layout>
