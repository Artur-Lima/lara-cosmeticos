@php($input = 'mt-1 block w-full rounded-md border-stone-300 text-sm focus:border-brand-500 focus:ring-brand-500')
<x-admin-layout :title="$category->exists ? 'Editar categoria' : 'Nova categoria'">
    <form method="POST" action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
          class="max-w-lg space-y-4 rounded-lg border border-stone-200 bg-white p-6">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <div>
            <label for="name" class="text-sm font-medium">Nome *</label>
            <input id="name" name="name" value="{{ old('name', $category->name) }}" required maxlength="120" class="{{ $input }}">
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <label for="parent_id" class="text-sm font-medium">Dentro de</label>
            <select id="parent_id" name="parent_id" class="{{ $input }}">
                <option value="">— categoria principal —</option>
                @foreach ($parents as $id => $name)
                    <option value="{{ $id }}" @selected((int) old('parent_id', $category->parent_id) === $id)>{{ $name }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('parent_id')" class="mt-1" />
        </div>
        <div>
            <label for="description" class="text-sm font-medium">Descrição</label>
            <input id="description" name="description" maxlength="255" value="{{ old('description', $category->description) }}" class="{{ $input }}">
        </div>
        <div class="w-32">
            <label for="position" class="text-sm font-medium">Ordem</label>
            <input id="position" name="position" type="number" min="0" value="{{ old('position', $category->position) }}" class="{{ $input }}">
        </div>
        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active)) class="rounded border-stone-300 text-brand-600">
            Ativa
        </label>

        <div class="flex items-center gap-3 pt-2">
            <button class="rounded-md bg-brand-700 px-5 py-2 text-sm font-medium text-white hover:bg-brand-800">Salvar</button>
            <a href="{{ route('admin.categories.index') }}" class="text-sm text-stone-600 hover:underline">Voltar</a>
            @if ($category->exists)
                <button type="submit" form="delete" class="ml-auto text-sm text-red-600 hover:underline">Excluir</button>
            @endif
        </div>
    </form>

    @if ($category->exists)
        <form id="delete" method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Excluir esta categoria?')">@csrf @method('DELETE')</form>
    @endif
</x-admin-layout>
