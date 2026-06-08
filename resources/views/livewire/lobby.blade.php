@script
<script>
    if (window.Echo) {
        window.Echo.join('room.{{ $code }}')
            .leaving(member => {
                console.log('[leaving] member:', member, 'id:', member.id);
                $wire.playerLeft(member.id);
            });
    }

    setInterval(() => {
        fetch('/api/heartbeat', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
        });
    }, 30000);
</script>
@endscript

<div class="mx-auto max-w-lg space-y-5">

    {{-- Header card --}}
    <x-card class="text-center space-y-4">
        <h1 class="font-display text-2xl font-black text-ink">Salle d'attente</h1>

        {{-- Copiable room code --}}
        <div
            x-data="{ copied: false }"
            x-on:click="navigator.clipboard.writeText('{{ $room->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
            class="inline-flex cursor-pointer flex-col items-center gap-1.5 group"
            title="Cliquer pour copier"
        >
            <span class="font-mono text-5xl font-black tracking-[0.3em] text-primary select-none group-hover:text-primary-dark transition-colors">
                {{ $room->code }}
            </span>
            <span x-show="!copied" class="text-xs text-muted flex items-center gap-1">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Cliquer pour copier
            </span>
            <span x-show="copied" x-cloak class="text-xs text-success font-semibold">✓ Copié !</span>
        </div>

        <x-badge variant="warning">⏳ En attente de joueurs...</x-badge>
    </x-card>

    {{-- Players list --}}
    <x-card class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="font-display font-black text-lg text-ink">Joueurs</h2>
            <x-badge>{{ $players->count() }} / {{ $room->max_players }}</x-badge>
        </div>

        <div class="space-y-2">
            @forelse ($players as $player)
                <div class="flex items-center gap-3 rounded-xl bg-zinc-50 px-4 py-3">
                    <x-avatar :name="$player->displayName()" />
                    <span class="flex-1 font-semibold text-ink text-sm">{{ $player->displayName() }}</span>
                    @if ($loop->first)
                        <x-badge variant="primary">👑 Hôte</x-badge>
                    @endif
                    @if ($player->id === $gamePlayerId)
                        <x-badge>Vous</x-badge>
                    @endif
                </div>
            @empty
                <p class="text-center text-sm text-muted py-4">Aucun joueur connecté.</p>
            @endforelse
        </div>
    </x-card>

    {{-- Start / waiting --}}
    @if ($isHost)
        <x-btn wire:click="startGame" size="lg" class="w-full">
            Lancer la partie 🚀
        </x-btn>
    @else
        <p class="text-center text-sm text-muted">
            En attente que l'hôte lance la partie...
        </p>
    @endif

</div>
