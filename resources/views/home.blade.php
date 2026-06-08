<x-layouts::app :title="__('Accueil')">

    {{-- Hero guest — le fond et les blobs viennent du layout. Les utilisateurs
         authentifiés sont routés vers la vue home.auth par HomeController. --}}
    <div class="flex flex-col items-center text-center py-20 gap-10 mx-auto max-w-3xl">

        {{-- Titre --}}
        <div class="space-y-4">
            <h1 class="font-display text-6xl sm:text-7xl font-black tracking-tight leading-tight bg-[linear-gradient(135deg,#7C5CBF,#10B981)] bg-clip-text text-transparent">
                BlindBeat
            </h1>
            <p class="text-lg text-muted max-w-md mx-auto leading-relaxed">
                Reconnais les titres, devance tes amis, domine le classement.
                Le tout en&nbsp;30&nbsp;secondes par manche.
            </p>
        </div>

        {{-- CTAs --}}
        <div class="flex flex-col sm:flex-row gap-3">
            <x-btn href="{{ route('rooms.create') }}" size="lg" class="animate-pulse-cta">
                🏠 Créer une room
            </x-btn>
            <x-btn href="{{ route('rooms.join') }}" variant="secondary" size="lg">
                🎮 Rejoindre
            </x-btn>
        </div>

        {{-- Feature cards --}}
        <div class="flex flex-wrap justify-center gap-4 w-full max-w-lg">
            <div class="flex-1 min-w-36 flex flex-col items-center gap-2 rounded-2xl px-4 py-5 bg-primary-light">
                <span class="text-3xl">🎶</span>
                <span class="text-sm font-semibold text-primary leading-tight text-center">Previews Deezer 30s</span>
            </div>
            <div class="flex-1 min-w-36 flex flex-col items-center gap-2 rounded-2xl px-4 py-5 bg-green-100">
                <span class="text-3xl">⚡</span>
                <span class="text-sm font-semibold text-green-700 leading-tight text-center">Temps réel</span>
            </div>
            <div class="flex-1 min-w-36 flex flex-col items-center gap-2 rounded-2xl px-4 py-5 bg-amber-100">
                <span class="text-3xl">🏆</span>
                <span class="text-sm font-semibold text-amber-700 leading-tight text-center">Classement live</span>
            </div>
        </div>

        <a href="{{ route('login') }}" class="text-sm text-muted hover:text-ink transition-colors">
            Déjà un compte ? Connexion
        </a>
    </div>

</x-layouts::app>
