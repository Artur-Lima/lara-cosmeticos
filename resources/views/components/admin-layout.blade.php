@props(['title' => 'Painel'])

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Admin · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600|instrument-sans:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-stone-50 text-ink">
@php
    $menu = [
        ['admin.dashboard', 'admin.dashboard', 'Painel'],
        ['admin.orders.index', 'admin.orders.*', 'Pedidos'],
        ['admin.products.index', 'admin.products.*', 'Produtos'],
        ['admin.brands.index', 'admin.brands.*', 'Marcas'],
        ['admin.categories.index', 'admin.categories.*', 'Categorias'],
        ['admin.shipping-zones.index', 'admin.shipping-zones.*', 'Entrega'],
    ];
@endphp
<div x-data="{ menu: false }" class="min-h-screen lg:flex">
    {{-- Barra lateral --}}
    <aside :class="menu ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 w-60 bg-white border-r border-stone-200 transition-transform lg:static lg:translate-x-0">
        <div class="h-16 flex items-center px-6 border-b border-stone-200">
            <a href="{{ route('admin.dashboard') }}"><x-application-logo class="text-xl" /></a>
        </div>
        <nav class="p-3 space-y-1 text-sm">
            @foreach ($menu as [$route, $pattern, $label])
                <a href="{{ route($route) }}"
                   @class([
                       'block rounded-md px-3 py-2 font-medium',
                       'bg-brand-50 text-brand-800' => request()->routeIs($pattern),
                       'text-stone-600 hover:bg-stone-100' => ! request()->routeIs($pattern),
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
        <div class="absolute bottom-0 inset-x-0 p-3 border-t border-stone-200 text-sm space-y-1">
            <a href="{{ route('home') }}" target="_blank" class="block rounded-md px-3 py-2 text-stone-600 hover:bg-stone-100">Ver loja ↗</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full text-left rounded-md px-3 py-2 text-stone-600 hover:bg-stone-100">Sair ({{ auth()->user()->name }})</button>
            </form>
        </div>
    </aside>
    <div x-show="menu" x-cloak @click="menu = false" class="fixed inset-0 z-30 bg-black/30 lg:hidden"></div>

    <div class="flex-1 min-w-0">
        <header class="h-16 flex items-center gap-3 px-4 sm:px-8 bg-white border-b border-stone-200">
            <button @click="menu = true" class="lg:hidden -ml-1 p-2 text-stone-600" aria-label="Abrir menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-lg font-semibold">{{ $title }}</h1>
            <div class="ml-auto flex items-center gap-2">{{ $actions ?? '' }}</div>
        </header>

        <main class="p-4 sm:p-8 max-w-6xl">
            @if (session('success'))
                <div class="mb-6 rounded-md bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="mb-6 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800">
                    Confira os campos destacados: {{ $errors->count() }} {{ $errors->count() === 1 ? 'erro' : 'erros' }}.
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
