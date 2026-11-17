<x-admin-layout title="Entrega em {{ \App\Models\ShippingZone::CITY }}">
    <x-slot name="actions">
        <a href="{{ route('admin.shipping-zones.create') }}" class="inline-flex items-center rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">+ Nova região</a>
    </x-slot>

    <p class="mb-4 text-sm text-stone-600">
        Cadastre os bairros ou regiões atendidos e a taxa de cada um. Retirada na loja é sempre gratuita.
        Para cobrar a mesma taxa na cidade toda, cadastre uma região só (ex.: "Toda a cidade").
    </p>

    <div class="overflow-hidden rounded-lg border border-stone-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-stone-50 text-left text-xs uppercase tracking-wide text-stone-500">
            <tr><th class="px-4 py-3">Região</th><th class="px-4 py-3">Taxa</th><th class="px-4 py-3">Grátis acima de</th><th class="px-4 py-3">Prazo</th><th class="px-4 py-3">Situação</th><th></th></tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
            @forelse ($zones as $zone)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $zone->name }}</td>
                    <td class="px-4 py-3">R$ {{ number_format($zone->fee, 2, ',', '.') }}</td>
                    <td class="px-4 py-3">{{ $zone->free_above ? 'R$ '.number_format($zone->free_above, 2, ',', '.') : '—' }}</td>
                    <td class="px-4 py-3">{{ $zone->estimate ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $zone->is_active ? 'Ativa' : 'Inativa' }}</td>
                    <td class="px-4 py-3 text-right"><a href="{{ route('admin.shipping-zones.edit', $zone) }}" class="text-brand-700 hover:underline">Editar</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-stone-500">Nenhuma região cadastrada: hoje só a retirada na loja está disponível.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
