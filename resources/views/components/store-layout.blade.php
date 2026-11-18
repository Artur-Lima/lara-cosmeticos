@props([
    'title' => null,
    'description' => 'Perfumes, maquiagem e cuidados Natura, O Boticário e Eudora em Itapuã do Oeste. Entrega no mesmo dia ou retirada na loja.',
    'image' => null,
])
@php
    $pageTitle = $title ? $title.' | '.config('shop.name') : config('shop.name').' | Perfumes e cosméticos em Itapuã do Oeste';
    $whatsapp = config('shop.whatsapp');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('shop.name') }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    @if ($image)<meta property="og:image" content="{{ $image }}">@endif
    <meta name="theme-color" content="#2e1a2b">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=cormorant-garamond:500,600|instrument-sans:400,500,600&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen flex flex-col bg-mist font-sans text-ink antialiased" x-data="{ drawer: false }">
    <a href="#conteudo" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Pular para o conteúdo</a>

    <p class="bg-ink px-4 py-2 text-center text-xs text-brand-100">
        Entrega no mesmo dia em {{ \App\Models\ShippingZone::CITY }} ou retirada grátis na loja
    </p>

    <header class="sticky top-0 z-30 border-b border-brand-100 bg-mist/95 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-6xl items-center gap-3 px-4 sm:gap-6 sm:px-6">
            <button type="button" @click="drawer = true" class="-ml-2 p-2 lg:hidden" aria-label="Abrir categorias">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
            </button>

            <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ config('shop.name') }}, página inicial">
                <x-application-logo class="text-2xl sm:text-[1.7rem]" />
            </a>

            <form action="{{ route('catalog') }}" method="GET" role="search" class="hidden flex-1 md:block">
                <label for="busca-topo" class="sr-only">Buscar produtos</label>
                <input id="busca-topo" type="search" name="busca" value="{{ request('busca') }}" placeholder="Buscar perfume, batom, marca…"
                       class="w-full max-w-md rounded-full border-brand-200 bg-white px-5 text-sm placeholder:text-brand-400 focus:border-brand-500 focus:ring-brand-500">
            </form>

            <nav class="ml-auto flex items-center gap-1 sm:gap-3 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-full px-3 py-2 hover:bg-quartz">{{ auth()->user()->isAdmin() ? 'Painel' : 'Minha conta' }}</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-full px-3 py-2 hover:bg-quartz">Entrar</a>
                @endauth
                <a href="{{ route('cart.index') }}" class="relative inline-flex items-center gap-2 rounded-full bg-ink px-4 py-2 font-medium text-white hover:bg-brand-900">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>
                    <span>Sacola</span>
                    @if ($cartCount > 0)
                        <span class="grid h-5 min-w-5 place-items-center rounded-full bg-brand-200 px-1 text-[11px] font-semibold text-ink" aria-label="{{ $cartCount }} itens">{{ $cartCount }}</span>
                    @endif
                </a>
            </nav>
        </div>

        @if ($menuCategories->isNotEmpty())
            <nav aria-label="Categorias" class="hidden border-t border-brand-100 lg:block">
                <ul class="mx-auto flex max-w-6xl gap-7 px-6 text-sm">
                    @foreach ($menuCategories as $menu)
                        <li><a href="{{ route('catalog.category', $menu) }}" @class(['block py-3 hover:text-brand-600', 'text-brand-600 font-medium' => request()->is('categoria/'.$menu->slug)])>{{ $menu->name }}</a></li>
                    @endforeach
                    <li class="ml-auto"><a href="{{ route('catalog') }}" class="block py-3 hover:text-brand-600">Ver tudo</a></li>
                </ul>
            </nav>
        @endif

        <form action="{{ route('catalog') }}" method="GET" role="search" class="border-t border-brand-100 px-4 py-2 md:hidden">
            <label for="busca-mobile" class="sr-only">Buscar produtos</label>
            <input id="busca-mobile" type="search" name="busca" value="{{ request('busca') }}" placeholder="Buscar perfume, batom, marca…"
                   class="w-full rounded-full border-brand-200 bg-white px-4 text-sm placeholder:text-brand-400 focus:border-brand-500 focus:ring-brand-500">
        </form>
    </header>

    {{-- Gaveta de categorias (mobile) --}}
    <div x-show="drawer" x-cloak class="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Categorias" @keydown.escape.window="drawer = false">
        <div class="absolute inset-0 bg-ink/40" @click="drawer = false"></div>
        <nav class="absolute inset-y-0 left-0 w-72 bg-mist p-6 shadow-xl" x-show="drawer" x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="flex items-center justify-between">
                <p class="font-display text-2xl">Categorias</p>
                <button type="button" @click="drawer = false" class="p-2" aria-label="Fechar">✕</button>
            </div>
            <ul class="mt-6 space-y-1 text-lg">
                @foreach ($menuCategories as $menu)
                    <li><a href="{{ route('catalog.category', $menu) }}" class="block rounded-lg px-3 py-2 hover:bg-quartz">{{ $menu->name }}</a></li>
                @endforeach
                <li><a href="{{ route('catalog') }}" class="block rounded-lg px-3 py-2 hover:bg-quartz">Ver tudo</a></li>
            </ul>
        </nav>
    </div>

    <main id="conteudo" class="flex-1">
        @if (session('success') || session('error') || session('cart_notices'))
            <div class="mx-auto max-w-6xl px-4 pt-4 sm:px-6 space-y-2" role="status">
                @if (session('success'))
                    <p class="rounded-lg bg-sage-50 px-4 py-3 text-sm text-sage-700">
                        {{ session('success') }}
                        @if (session('cart_added')) <a href="{{ route('cart.index') }}" class="ml-2 font-medium underline">Ver sacola</a> @endif
                    </p>
                @endif
                @if (session('error'))
                    <p class="rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</p>
                @endif
                @foreach ((array) session('cart_notices') as $notice)
                    <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $notice }}</p>
                @endforeach
            </div>
        @endif

        {{ $slot }}
    </main>

    <footer class="mt-20 bg-ink text-brand-100">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 sm:grid-cols-3 sm:px-6">
            <div>
                <p class="font-display text-3xl leading-none text-white">Lara Lima<span class="block font-sans text-[0.45em] uppercase tracking-[0.35em] text-brand-300">Cosméticos</span></p>
                <p class="mt-4 max-w-xs text-sm text-brand-200">Perfumaria e cosméticos em {{ \App\Models\ShippingZone::CITY }}. Revenda de Natura, O Boticário e Eudora.</p>
            </div>
            <div class="text-sm space-y-2">
                <p class="font-display text-xl text-white">Atendimento</p>
                @if ($whatsapp)
                    <p><a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="hover:text-white underline-offset-4 hover:underline">WhatsApp {{ \App\Support\Phone::format(substr($whatsapp, 2)) }}</a></p>
                @endif
                @if (config('shop.instagram'))
                    <p><a href="https://instagram.com/{{ config('shop.instagram') }}" target="_blank" rel="noopener" class="hover:text-white underline-offset-4 hover:underline">Instagram {{ '@'.config('shop.instagram') }}</a></p>
                @endif
                @if (config('shop.opening_hours'))<p>{{ config('shop.opening_hours') }}</p>@endif
            </div>
            <div class="text-sm space-y-2">
                <p class="font-display text-xl text-white">Retirada na loja</p>
                <p>{{ config('shop.address') }}</p>
                <p class="pt-4 text-xs text-brand-300">Pagamento por PIX ou cartão de crédito.</p>
            </div>
        </div>
        <p class="border-t border-white/10 py-5 text-center text-xs text-brand-300">© {{ date('Y') }} {{ config('shop.name') }}</p>
    </footer>
</body>
</html>
