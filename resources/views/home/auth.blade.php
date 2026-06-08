<x-layouts::app :title="__('Accueil')">
    <div class="mx-auto max-w-3xl space-y-8">

        {{-- Greeting --}}
        <div>
            <h1 class="font-display text-3xl font-black text-ink">
                Bonjour, {{ auth()->user()->name }} 👋
            </h1>
            <p class="text-muted mt-1">Prêt pour une partie ?</p>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <x-card padding="sm" class="text-center">
                <p class="font-black text-2xl text-primary">{{ $stats['groups_count'] }}</p>
                <p class="text-[11px] uppercase tracking-widest text-muted mt-0.5">Groupes</p>
            </x-card>
            <x-card padding="sm" class="text-center">
                <p class="font-black text-2xl text-primary">{{ $stats['games_played'] }}</p>
                <p class="text-[11px] uppercase tracking-widest text-muted mt-0.5">Parties</p>
            </x-card>
            <x-card padding="sm" class="text-center">
                <p class="font-black text-2xl text-primary">{{ $stats['total_points'] }}</p>
                <p class="text-[11px] uppercase tracking-widest text-muted mt-0.5">Pts cumulés</p>
            </x-card>
            <x-card padding="sm" class="text-center">
                <p class="font-black text-2xl text-primary">{{ $stats['best_score'] }}</p>
                <p class="text-[11px] uppercase tracking-widest text-muted mt-0.5">Record</p>
            </x-card>
        </div>

        {{-- Quick actions --}}
        <div class="grid sm:grid-cols-2 gap-5">
            <x-card class="space-y-4">
                <div class="text-4xl">🏠</div>
                <div>
                    <h2 class="font-display font-black text-xl text-ink">Créer une room</h2>
                    <p class="text-sm text-muted mt-1">Choisis tes thèmes et invite tes amis.</p>
                </div>
                <x-btn href="{{ route('rooms.create') }}" class="w-full">
                    Créer une room
                </x-btn>
            </x-card>

            <x-card class="space-y-4">
                <div class="text-4xl">🎮</div>
                <div>
                    <h2 class="font-display font-black text-xl text-ink">Rejoindre</h2>
                    <p class="text-sm text-muted mt-1">Entre le code et rejoins la partie.</p>
                </div>
                <x-btn href="{{ route('rooms.join') }}" variant="secondary" class="w-full">
                    Rejoindre une partie
                </x-btn>
            </x-card>
        </div>

        {{-- Groupes --}}
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="font-display font-black text-xs text-muted uppercase tracking-widest">Mes groupes</h2>
                <a href="{{ route('groups.index') }}" class="text-xs font-medium text-primary hover:text-primary-dark transition-colors">Tout voir →</a>
            </div>

            @forelse ($groups as $group)
                <a href="{{ route('groups.show', $group) }}" class="block">
                    <x-card padding="sm" class="transition hover:shadow-raised">
                        <div class="flex items-center gap-4">
                            <span class="text-2xl">👥</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-display font-black text-ink truncate">{{ $group->name }}</p>
                                <p class="text-xs text-muted mt-0.5">
                                    <span class="font-mono font-semibold tracking-wider">{{ $group->code }}</span>
                                    · {{ $group->members->count() }} membre{{ $group->members->count() > 1 ? 's' : '' }}
                                </p>
                            </div>
                            <span class="text-muted text-sm shrink-0">→</span>
                        </div>
                    </x-card>
                </a>
            @empty
                <x-card class="py-8 text-center space-y-2">
                    <p class="text-muted text-sm">Vous n'avez encore aucun groupe.</p>
                    <x-btn :href="route('groups.create')" size="sm" class="mt-1">Créer un groupe</x-btn>
                </x-card>
            @endforelse
        </div>

    </div>
</x-layouts::app>
