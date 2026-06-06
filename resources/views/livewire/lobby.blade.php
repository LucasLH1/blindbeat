@script
<script>
    if (window.Echo) {
        window.Echo.join('room.{{ $code }}')
            .leaving(member => $wire.playerLeft(member.id));
    }
</script>
@endscript

<div class="mx-auto max-w-xl space-y-6 py-10">

    {{-- Header --}}
    <flux:card class="space-y-4 text-center">
        <flux:heading size="xl" class="truncate">{{ $room->playlist?->name }}</flux:heading>

        {{-- Copiable room code --}}
        <div
            x-data="{ copied: false }"
            x-on:click="navigator.clipboard.writeText('{{ $room->code }}'); copied = true; setTimeout(() => copied = false, 2000)"
            class="inline-flex cursor-pointer flex-col items-center gap-1"
            title="Cliquer pour copier"
        >
            <span class="font-mono text-5xl font-bold tracking-[0.3em] text-violet-500">
                {{ $room->code }}
            </span>
            <span x-show="!copied" class="text-xs text-zinc-400">Cliquer pour copier le code</span>
            <span x-show="copied" x-cloak class="text-xs text-emerald-500">Copié !</span>
        </div>

        <flux:badge color="amber" size="lg" class="mx-auto">
            En attente de joueurs...
        </flux:badge>
    </flux:card>

    {{-- Player list --}}
    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <flux:heading size="lg">Joueurs</flux:heading>
            <flux:badge color="zinc">{{ $players->count() }} / {{ $room->max_players }}</flux:badge>
        </div>

        <div class="space-y-3">
            @forelse ($players as $player)
                <div class="flex items-center gap-3 rounded-xl bg-zinc-50 px-4 py-3">
                    <flux:avatar
                        name="{{ $player->displayName() }}"
                        class="shrink-0"
                    />
                    <span class="flex-1 font-medium text-zinc-800">
                        {{ $player->displayName() }}
                    </span>
                    @if ($loop->first)
                        <flux:badge color="violet" size="sm">Hôte</flux:badge>
                    @endif
                    @if ($player->id === $gamePlayerId)
                        <flux:badge color="sky" size="sm">Vous</flux:badge>
                    @endif
                </div>
            @empty
                <flux:text class="text-center text-zinc-400">Aucun joueur connecté.</flux:text>
            @endforelse
        </div>
    </flux:card>

    {{-- Start button (host only) --}}
    @if ($isHost)
        <flux:button
            wire:click="startGame"
            variant="primary"
            class="w-full"
            :disabled="$players->count() < 2"
        >
            @if ($players->count() < 2)
                En attente d'un autre joueur...
            @else
                Lancer la partie
            @endif
        </flux:button>
    @else
        <flux:text class="text-center text-zinc-400">
            En attente que l'hôte lance la partie...
        </flux:text>
    @endif

</div>
