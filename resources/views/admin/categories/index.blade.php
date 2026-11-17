<x-admin-layout title="Categorias">
    <x-slot name="actions">
        <a href="{{ route('admin.categories.create') }}" class="inline-flex items-center rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">+ Nova categoria</a>
    </x-slot>

    <div class="overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
            <tr><th class="px-4 py-3">Categoria</th><th class="px-4 py-3">Produtos</th><th class="px-4 py-3">Situação</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($categories as $category)
                @foreach ([$category, ...$category->children] as $item)
                    <tr @class(['bg-stone-50/50' => $item->parent_id === null])>
                        <td @class(['px-4 py-3', 'font-semibold' => ! $item->parent_id, 'pl-10 text-stone-700' => $item->parent_id])>
                            {{ $item->parent_id ? '↳ ' : '' }}{{ $item->name }}
                        </td>
                        <td class="px-4 py-3">{{ $item->products_count }}</td>
                        <td class="px-4 py-3">{{ $item->is_active ? 'Ativa' : 'Inativa' }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('admin.categories.edit', $item) }}" class="text-brand-700 hover:underline">Editar</a></td>
                    </tr>
                @endforeach
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-stone-500">Nenhuma categoria cadastrada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
