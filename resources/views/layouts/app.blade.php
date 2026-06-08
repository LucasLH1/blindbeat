<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ filled($title ?? null) ? $title.' — '.config('app.name') : config('app.name') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen text-ink antialiased" style="background: linear-gradient(135deg, #EDE9FF 0%, #E8F8F0 50%, #FFF0E8 100%)">

    {{-- Blobs décoratifs — position:fixed, restent visibles au scroll --}}
    <div class="fixed inset-0 pointer-events-none select-none overflow-hidden" style="z-index: 0" aria-hidden="true">
        <svg class="absolute -top-32 -right-32 w-96 h-96 opacity-[0.22]" viewBox="0 0 400 400">
            <circle cx="200" cy="200" r="200" fill="#7C5CBF"/>
        </svg>
        <svg class="absolute -bottom-24 -left-24 w-80 h-80 opacity-[0.18]" viewBox="0 0 320 320">
            <circle cx="160" cy="160" r="160" fill="#10B981"/>
        </svg>
        <svg class="absolute top-8 -left-12 w-52 h-52 opacity-[0.22]" viewBox="0 0 200 200">
            <circle cx="100" cy="100" r="100" fill="#F97316"/>
        </svg>
        <svg class="absolute bottom-16 right-16 w-24 h-24 opacity-[0.20]" viewBox="0 0 100 100">
            <circle cx="50" cy="50" r="50" fill="#F59E0B"/>
        </svg>
        <svg class="absolute top-1/2 -left-4 w-14 h-14 opacity-[0.18]" viewBox="0 0 60 60">
            <circle cx="30" cy="30" r="30" fill="#EC4899"/>
        </svg>
    </div>

    {{-- Navbar --}}
    <header class="sticky top-0 z-50 bg-white/80 backdrop-blur-md shadow-card" style="z-index: 50">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ route('home') }}" class="font-display font-black text-xl tracking-tight select-none flex items-center gap-1">
                <span>🎵</span>
                <span class="bg-[linear-gradient(135deg,#7C5CBF,#10B981)] bg-clip-text text-transparent">Blindtest</span>
            </a>

            <nav class="flex items-center gap-4">
                @auth
                    <a href="{{ route('groups.index') }}" class="text-sm font-medium text-primary hover:text-primary-dark transition-colors">
                        Mes groupes
                    </a>
                    <span class="hidden sm:block text-sm text-muted">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-sm text-muted hover:text-ink transition-colors cursor-pointer">
                            Déconnexion
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-primary hover:text-primary-dark transition-colors">
                        Connexion
                    </a>
                    <a href="{{ route('register') }}" class="inline-flex items-center px-4 py-1.5 rounded-full bg-primary text-white text-sm font-semibold hover:bg-primary-dark transition-colors">
                        S'inscrire
                    </a>
                @endauth
            </nav>
        </div>
        <div class="h-[2px] bg-[linear-gradient(90deg,#7C5CBF_0%,#10B981_100%)]"></div>
    </header>

    {{-- Contenu — au-dessus des blobs --}}
    <main class="relative px-4 sm:px-6 py-8" style="z-index: 10">
        {{ $slot }}
    </main>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @auth
        <livewire:group-notifications />
    @endauth

    @fluxScripts
</body>
</html>
