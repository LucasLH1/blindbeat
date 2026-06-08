<x-layouts::app :title="__('Mes groupes')">
    <div class="mx-auto max-w-2xl space-y-6">

        <div class="flex items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-3xl font-black text-ink">Mes groupes</h1>
                <p class="text-muted mt-1">Jouez en équipe et grimpez dans le classement.</p>
            </div>
            <x-btn :href="route('groups.create')" size="sm">+ Créer</x-btn>
        </div>

        {{-- Rejoindre via code --}}
        <x-card padding="sm">
            <form method="POST" action="{{ route('groups.join') }}" class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <label class="block text-sm font-semibold text-ink mb-1.5">Rejoindre un groupe</label>
                    <x-input name="code" value="{{ old('code') }}" maxlength="6" placeholder="Code à 6 caractères" class="uppercase" />
                    @error('code')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>
                <x-btn type="submit" variant="ghost">Rejoindre</x-btn>
            </form>
        </x-card>

        {{-- Liste des groupes --}}
        <div class="space-y-3">
            @forelse ($groups as $group)
                @php $myScore = $myScores[$group->id] ?? null; @endphp
                <a href="{{ route('groups.show', $group) }}" class="block">
                    <x-card padding="sm" class="transition hover:shadow-raised">
                        <div class="flex items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <p class="font-display font-black text-ink truncate">{{ $group->name }}</p>
                                <p class="text-xs text-muted mt-0.5">
                                    <span class="font-mono font-semibold tracking-wider">{{ $group->code }}</span>
                                    · {{ $group->members->count() }} membre{{ $group->members->count() > 1 ? 's' : '' }}
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="font-black text-primary text-lg">{{ $myScore ? round($myScore->total_normalized_points, 1) : 0 }}</p>
                                <p class="text-[10px] uppercase tracking-widest text-muted">pts cumulés</p>
                            </div>
                        </div>
                    </x-card>
                </a>
            @empty
                <x-card class="py-12 text-center space-y-2">
                    <div class="text-4xl">👥</div>
                    <p class="text-muted">Vous n'avez encore aucun groupe.</p>
                    <x-btn :href="route('groups.create')" size="sm" class="mt-2">Créer mon premier groupe</x-btn>
                </x-card>
            @endforelse
        </div>

    </div>
</x-layouts::app>
