@php($input = 'mt-1 block w-full rounded-md border-stone-300 text-sm focus:border-brand-500 focus:ring-brand-500')
<x-admin-layout :title="$zone->exists ? 'Editar região de entrega' : 'Nova região de entrega'">
    <form method="POST" action="{{ $zone->exists ? route('admin.shipping-zones.update', $zone) : route('admin.shipping-zones.store') }}"
          class="max-w-lg space-y-4 rounded-lg border border-stone-200 bg-white p-6">
        @csrf
        @if ($zone->exists) @method('PUT') @endif

        <div>
            <label for="name" class="text-sm font-medium">Bairro ou região *</label>
            <input id="name" name="name" value="{{ old('name', $zone->name) }}" required maxlength="80" class="{{ $input }}" placeholder="Centro">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="fee" class="text-sm font-medium">Taxa (R$) *</label>
                <input id="fee" name="fee" type="number" step="0.01" min="0" value="{{ old('fee', $zone->fee) }}" required class="{{ $input }}">
                <x-input-error :messages="$errors->get('fee')" class="mt-1" />
            </div>
            <div>
                <label for="free_above" class="text-sm font-medium">Grátis acima de (R$)</label>
                <input id="free_above" name="free_above" type="number" step="0.01" min="0.01" value="{{ old('free_above', $zone->free_above) }}" class="{{ $input }}" placeholder="opcional">
                <x-input-error :messages="$errors->get('free_above')" class="mt-1" />
            </div>
        </div>
        <div>
            <label for="estimate" class="text-sm font-medium">Prazo exibido ao cliente</label>
            <input id="estimate" name="estimate" maxlength="60" value="{{ old('estimate', $zone->estimate) }}" class="{{ $input }}" placeholder="no mesmo dia">
        </div>
        <div class="w-32">
            <label for="position" class="text-sm font-medium">Ordem</label>
            <input id="position" name="position" type="number" min="0" value="{{ old('position', $zone->position) }}" class="{{ $input }}">
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $zone->is_active)) class="rounded border-stone-300 text-brand-600">
            Ativa
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button class="rounded-md bg-brand-700 px-5 py-2 text-sm font-medium text-white hover:bg-brand-800">Salvar</button>
            <a href="{{ route('admin.shipping-zones.index') }}" class="text-sm text-stone-600 hover:underline">Voltar</a>
            @if ($zone->exists)
                <button type="submit" form="delete" class="ml-auto text-sm text-red-600 hover:underline">Excluir</button>
            @endif
        </div>
    </form>

    @if ($zone->exists)
        <form id="delete" method="POST" action="{{ route('admin.shipping-zones.destroy', $zone) }}" onsubmit="return confirm('Excluir esta região?')">@csrf @method('DELETE')</form>
    @endif
</x-admin-layout>
