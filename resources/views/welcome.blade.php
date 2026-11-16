<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500|playfair-display:600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-50 text-ink font-sans antialiased flex items-center justify-center p-6">
    <main class="text-center max-w-md">
        <p class="text-xs uppercase tracking-[0.3em] text-brand-600">Em breve</p>
        <h1 class="mt-3 font-display text-4xl text-brand-900">{{ config('app.name') }}</h1>
        <p class="mt-4 text-sm text-brand-800/80">Perfumaria e cosméticos. Nossa loja online está sendo preparada.</p>
    </main>
</body>
</html>
