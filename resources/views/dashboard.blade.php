<x-app-layout>
    <x-slot name="header">
        <h2 class="font-display text-2xl text-brand-900 leading-tight">Olá, {{ str(auth()->user()->name)->before(' ') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white rounded-lg border border-brand-100 p-6">
                <h3 class="font-semibold">Meus pedidos</h3>
                <p class="mt-1 text-sm text-gray-600">Você ainda não fez nenhum pedido.</p>
                <a href="{{ route('home') }}" class="mt-4 inline-flex rounded-md bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">Ver produtos</a>
            </div>
            <a href="{{ route('profile.edit') }}" class="block bg-white rounded-lg border border-brand-100 p-6 hover:border-brand-300">
                <h3 class="font-semibold">Meus dados</h3>
                <p class="mt-1 text-sm text-gray-600">Nome, e-mail, WhatsApp e senha.</p>
            </a>
        </div>
    </div>
</x-app-layout>
