@php
    $editing = $product->exists;
    $input = 'mt-1 block w-full rounded-md border-stone-300 text-sm focus:border-brand-500 focus:ring-brand-500';
    $variantErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'variants.'))->map(fn ($m) => $m[0]);
@endphp

<x-admin-layout :title="$editing ? 'Editar produto' : 'Novo produto'">
    @if ($editing)
        <x-slot name="actions">
            <span class="text-xs text-stone-500 hidden sm:inline">/{{ $product->slug }}</span>
        </x-slot>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}"
          class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Dados do produto --}}
        <section class="rounded-lg border border-stone-200 bg-white p-5 sm:p-6">
            <h2 class="font-semibold">Informações</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="name" class="text-sm font-medium">Nome do produto *</label>
                    <input id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="180" class="{{ $input }}" placeholder="Ex.: Kaiak Aventura Desodorante Colônia Feminino">
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <label for="brand_id" class="text-sm font-medium">Marca</label>
                    <select id="brand_id" name="brand_id" class="{{ $input }}">
                        <option value="">— sem marca —</option>
                        @foreach ($brands as $id => $name)
                            <option value="{{ $id }}" @selected((int) old('brand_id', $product->brand_id) === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('brand_id')" class="mt-1" />
                </div>
                <div>
                    <label for="category_id" class="text-sm font-medium">Categoria</label>
                    <select id="category_id" name="category_id" class="{{ $input }}">
                        <option value="">— sem categoria —</option>
                        @foreach ($categories as $id => $name)
                            <option value="{{ $id }}" @selected((int) old('category_id', $product->category_id) === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('category_id')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <label for="short_description" class="text-sm font-medium">Descrição curta</label>
                    <input id="short_description" name="short_description" maxlength="255" value="{{ old('short_description', $product->short_description) }}" class="{{ $input }}" placeholder="Uma frase que aparece no card da vitrine">
                    <x-input-error :messages="$errors->get('short_description')" class="mt-1" />
                </div>
                <div class="sm:col-span-2">
                    <label for="description" class="text-sm font-medium">Descrição completa</label>
                    <textarea id="description" name="description" rows="5" class="{{ $input }}">{{ old('description', $product->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>
                <div>
                    <label for="how_to_use" class="text-sm font-medium">Modo de uso</label>
                    <textarea id="how_to_use" name="how_to_use" rows="3" class="{{ $input }}">{{ old('how_to_use', $product->how_to_use) }}</textarea>
                </div>
                <div>
                    <label for="composition" class="text-sm font-medium">Composição</label>
                    <textarea id="composition" name="composition" rows="3" class="{{ $input }}">{{ old('composition', $product->composition) }}</textarea>
                </div>
                <div>
                    <label for="gender" class="text-sm font-medium">Público</label>
                    <select id="gender" name="gender" class="{{ $input }}">
                        <option value="">— não se aplica —</option>
                        @foreach (\App\Enums\ProductGender::cases() as $gender)
                            <option value="{{ $gender->value }}" @selected(old('gender', $product->gender?->value) === $gender->value)>{{ $gender->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status" class="text-sm font-medium">Status *</label>
                    <select id="status" name="status" class="{{ $input }}">
                        @foreach (\App\Enums\ProductStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $product->status?->value) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-stone-500">Só aparece na loja quando "Publicado".</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $product->is_featured)) class="rounded border-stone-300 text-brand-600 focus:ring-brand-500">
                    Destacar na página inicial
                </label>
            </div>
        </section>

        {{-- Variantes --}}
        <section class="rounded-lg border border-stone-200 bg-white p-5 sm:p-6"
                 x-data="variantEditor(@js($variants), @js($defaultVariant), @js($variantErrors))">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="font-semibold">Variações, preço e estoque *</h2>
                    <p class="text-xs text-stone-500">Uma linha por tamanho (30 ml, 100 ml) ou tom (Nude 01). Produto único: deixe uma linha só.</p>
                </div>
                <button type="button" @click="add()" class="rounded-md border border-stone-300 px-3 py-1.5 text-sm font-medium hover:bg-stone-50">+ Variação</button>
            </div>
            <x-input-error :messages="$errors->get('variants')" class="mt-2" />
            <x-input-error :messages="$errors->get('default_variant')" class="mt-2" />

            <div class="mt-4 space-y-3">
                <template x-for="(v, i) in rows" :key="v._key">
                    <div class="rounded-md border p-4" :class="defaultIndex === i ? 'border-brand-300 bg-brand-50/40' : 'border-stone-200'">
                        <input type="hidden" :name="`variants[${i}][id]`" :value="v.id ?? ''">
                        <div class="grid gap-3 sm:grid-cols-6">
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium">Nome da variação *</label>
                                <input :name="`variants[${i}][label]`" x-model="v.label" required maxlength="80" class="{{ $input }}" placeholder="100 ml">
                                <p class="mt-1 text-xs text-red-600" x-text="err(i, 'label')"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium">SKU *</label>
                                <input :name="`variants[${i}][sku]`" x-model="v.sku" required maxlength="60" class="{{ $input }} font-mono uppercase" placeholder="NAT-KAIAK-100">
                                <p class="mt-1 text-xs text-red-600" x-text="err(i, 'sku')"></p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-xs font-medium">Código de barras</label>
                                <input :name="`variants[${i}][barcode]`" x-model="v.barcode" maxlength="20" inputmode="numeric" class="{{ $input }}">
                            </div>

                            <div>
                                <label class="text-xs font-medium">Preço (R$) *</label>
                                <input type="number" step="0.01" min="0.01" :name="`variants[${i}][price]`" x-model="v.price" required class="{{ $input }}">
                                <p class="mt-1 text-xs text-red-600" x-text="err(i, 'price')"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium">Preço "de" (R$)</label>
                                <input type="number" step="0.01" min="0.01" :name="`variants[${i}][compare_at_price]`" x-model="v.compare_at_price" class="{{ $input }}" placeholder="opcional">
                                <p class="mt-1 text-xs text-red-600" x-text="err(i, 'compare_at_price')"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium">Estoque *</label>
                                <input type="number" min="0" step="1" :name="`variants[${i}][stock]`" x-model="v.stock" required class="{{ $input }}">
                                <p class="mt-1 text-xs text-red-600" x-text="err(i, 'stock')"></p>
                            </div>
                            <div>
                                <label class="text-xs font-medium">Volume (ml)</label>
                                <input type="number" min="1" :name="`variants[${i}][volume_ml]`" x-model="v.volume_ml" class="{{ $input }}">
                            </div>
                            <div>
                                <label class="text-xs font-medium">Tom / cor</label>
                                <input :name="`variants[${i}][shade]`" x-model="v.shade" maxlength="60" class="{{ $input }}" placeholder="Nude 01">
                            </div>
                            <div>
                                <label class="text-xs font-medium">Amostra da cor</label>
                                <div class="mt-1 flex items-center gap-2">
                                    <input type="color" :value="v.color_hex || '#ffffff'" @input="v.color_hex = $event.target.value" class="h-9 w-12 rounded border border-stone-300">
                                    <input type="hidden" :name="`variants[${i}][color_hex]`" :value="v.color_hex ?? ''">
                                    <button type="button" x-show="v.color_hex" @click="v.color_hex = null" class="text-xs text-stone-500 hover:underline">limpar</button>
                                </div>
                                <p class="mt-1 text-xs text-red-600" x-text="err(i, 'color_hex')"></p>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-4 text-sm">
                            <label class="inline-flex items-center gap-2">
                                <input type="radio" name="default_variant" :value="i" :checked="defaultIndex === i" @change="defaultIndex = i" class="border-stone-300 text-brand-600 focus:ring-brand-500">
                                Exibir por padrão
                            </label>
                            <label class="inline-flex items-center gap-2">
                                <input type="hidden" :name="`variants[${i}][is_active]`" :value="v.is_active ? 1 : 0">
                                <input type="checkbox" x-model="v.is_active" class="rounded border-stone-300 text-brand-600 focus:ring-brand-500">
                                Ativa
                            </label>
                            <button type="button" x-show="rows.length > 1" @click="remove(i)" class="ml-auto text-red-600 hover:underline">Remover variação</button>
                        </div>
                    </div>
                </template>
            </div>
        </section>

        {{-- Fotos --}}
        <section class="rounded-lg border border-stone-200 bg-white p-5 sm:p-6">
            <h2 class="font-semibold">Fotos</h2>
            @if ($editing && $product->images->isNotEmpty())
                <div class="mt-4 grid grid-cols-3 sm:grid-cols-6 gap-3">
                    @foreach ($product->images as $image)
                        <div class="relative group">
                            <img src="{{ $image->url }}" alt="{{ $image->alt }}" class="aspect-square w-full rounded object-cover bg-stone-100">
                            @if ($loop->first)<span class="absolute left-1 top-1 rounded bg-white/90 px-1.5 text-[10px] font-medium">capa</span>@endif
                            <button type="submit" form="delete-image-{{ $image->id }}"
                                    class="absolute right-1 top-1 rounded bg-white/90 px-1.5 text-xs text-red-600 opacity-100 sm:opacity-0 group-hover:opacity-100">remover</button>
                        </div>
                    @endforeach
                </div>
            @endif
            <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp"
                   class="mt-4 block w-full text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-brand-800 hover:file:bg-brand-100">
            <p class="mt-1 text-xs text-stone-500">JPG, PNG ou WEBP, até 5 MB cada, até 10 por envio. A primeira foto é a capa.</p>
            <x-input-error :messages="collect($errors->get('images.*'))->flatten()->unique()->all()" class="mt-1" />
        </section>

        {{-- SEO --}}
        <details class="rounded-lg border border-stone-200 bg-white p-5 sm:p-6">
            <summary class="cursor-pointer font-semibold">Endereço e Google (opcional)</summary>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="slug" class="text-sm font-medium">Endereço da página</label>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <span class="inline-flex items-center rounded-l-md border border-r-0 border-stone-300 bg-stone-50 px-3 text-xs text-stone-500">/produto/</span>
                        <input id="slug" name="slug" value="{{ old('slug', $product->slug) }}" class="block w-full rounded-none rounded-r-md border-stone-300 text-sm focus:border-brand-500 focus:ring-brand-500" placeholder="gerado a partir do nome">
                    </div>
                    <x-input-error :messages="$errors->get('slug')" class="mt-1" />
                </div>
                <div>
                    <label for="seo_title" class="text-sm font-medium">Título no Google</label>
                    <input id="seo_title" name="seo_title" maxlength="70" value="{{ old('seo_title', $product->seo_title) }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="seo_description" class="text-sm font-medium">Descrição no Google</label>
                    <input id="seo_description" name="seo_description" maxlength="160" value="{{ old('seo_description', $product->seo_description) }}" class="{{ $input }}">
                </div>
            </div>
        </details>

        <div class="flex items-center gap-3">
            <button class="rounded-md bg-brand-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-brand-800">{{ $editing ? 'Salvar alterações' : 'Cadastrar produto' }}</button>
            <a href="{{ route('admin.products.index') }}" class="text-sm text-stone-600 hover:underline">Voltar</a>
            @if ($editing)
                <button type="submit" form="delete-product" class="ml-auto text-sm text-red-600 hover:underline">Excluir produto</button>
            @endif
        </div>
    </form>

    {{-- Formulários auxiliares fora do form principal (HTML não permite form aninhado) --}}
    @if ($editing)
        <form id="delete-product" method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Excluir este produto? Ele sai da loja, mas pedidos antigos continuam íntegros.')">
            @csrf @method('DELETE')
        </form>
        @foreach ($product->images as $image)
            <form id="delete-image-{{ $image->id }}" method="POST" action="{{ route('admin.products.images.destroy', [$product, $image]) }}" onsubmit="return confirm('Remover esta foto?')">
                @csrf @method('DELETE')
            </form>
        @endforeach
    @endif
</x-admin-layout>
