@php($input = 'mt-1 block w-full rounded-md border-stone-300 text-sm focus:border-brand-500 focus:ring-brand-500')
<x-admin-layout :title="$brand->exists ? 'Editar marca' : 'Nova marca'">
    <form method="POST" action="{{ $brand->exists ? route('admin.brands.update', $brand) : route('admin.brands.store') }}"
          class="max-w-lg space-y-4 rounded-lg border border-stone-200 bg-white p-6">
        @csrf
        @if ($brand->exists) @method('PUT') @endif

        <div>
            <label for="name" class="text-sm font-medium">Nome *</label>
            <input id="name" name="name" value="{{ old('name', $brand->name) }}" required maxlength="120" class="{{ $input }}">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active)) class="rounded border-stone-300 text-brand-600">
            Ativa
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button class="rounded-md bg-brand-700 px-5 py-2 text-sm font-medium text-white hover:bg-brand-800">Salvar</button>
            <a href="{{ route('admin.brands.index') }}" class="text-sm text-stone-600 hover:underline">Voltar</a>
            @if ($brand->exists)
                <button type="submit" form="delete" class="ml-auto text-sm text-red-600 hover:underline">Excluir</button>
            @endif
        </div>
    </form>

    @if ($brand->exists)
        <form id="delete" method="POST" action="{{ route('admin.brands.destroy', $brand) }}" onsubmit="return confirm('Excluir esta marca?')">@csrf @method('DELETE')</form>
    @endif
</x-admin-layout>
