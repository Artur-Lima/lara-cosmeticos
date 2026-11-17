<x-admin-layout title="Marcas">
    <x-slot name="actions">
        <a href="{{ route('admin.brands.create') }}" class="inline-flex items-center rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">+ Nova marca</a>
    </x-slot>

    <div class="overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
            <tr><th class="px-4 py-3">Marca</th><th class="px-4 py-3">Produtos</th><th class="px-4 py-3">Situação</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($brands as $brand)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $brand->name }}</td>
                    <td class="px-4 py-3">{{ $brand->products_count }}</td>
                    <td class="px-4 py-3">{{ $brand->is_active ? 'Ativa' : 'Inativa' }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.brands.edit', $brand) }}" class="text-brand-700 hover:underline">Editar</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-stone-500">Nenhuma marca cadastrada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
