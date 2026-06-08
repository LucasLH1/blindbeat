<x-layouts::app :title="$group->name">
    <div class="mx-auto max-w-2xl space-y-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <a href="{{ route('groups.index') }}" class="text-xs text-muted hover:text-ink transition-colors">← Mes groupes</a>
                <h1 class="font-display text-3xl font-black text-ink truncate">{{ $group->name }}</h1>
                <p class="text-muted mt-1 text-sm">
                    Code d'invitation :
                    <span class="font-mono font-bold tracking-widest text-primary select-all">{{ $group->code }}</span>
                </p>
            </div>
            <form method="POST" action="{{ route('groups.leave', $group) }}"
                  onsubmit="return confirm('Quitter ce groupe ?')">
                @csrf
                @method('DELETE')
                <x-btn type="submit" variant="ghost" size="sm">Quitter</x-btn>
            </form>
        </div>

        @error('leave')
            <p class="text-xs text-error font-medium">{{ $message }}</p>
        @enderror

        {{-- Classement --}}
        <x-card>
            <h2 class="font-display font-black text-xs text-muted uppercase tracking-widest mb-3">Classement</h2>
            <div class="space-y-1.5">
                @foreach ($leaderboard as $rank => $score)
                    <div class="flex items-center gap-3 rounded-xl px-3 py-2.5 {{ $rank === 0 ? 'bg-primary-light' : 'bg-zinc-50' }}">
                        <span class="w-6 shrink-0 text-center text-sm">
                            @if ($rank === 0) 🥇 @elseif ($rank === 1) 🥈 @elseif ($rank === 2) 🥉
                            @else <span class="text-xs font-bold text-muted">{{ $rank + 1 }}</span> @endif
                        </span>
                        <x-avatar :name="$score->user?->name ?? '?'" size="sm" />
                        <div class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold text-ink truncate">{{ $score->user?->name ?? 'Inconnu' }}</span>
                            <span class="block text-xs text-muted">
                                {{ $score->games_played }} partie{{ $score->games_played > 1 ? 's' : '' }}
                                · record {{ round($score->best_normalized_score, 1) }}
                            </span>
                        </div>
                        <span class="font-black text-base {{ $rank === 0 ? 'text-primary' : 'text-zinc-600' }} shrink-0">
                            {{ round($score->total_normalized_points, 1) }}
                        </span>
                    </div>
                @endforeach
            </div>
        </x-card>

        {{-- Lancer une partie --}}
        <x-card>
            <h2 class="font-display font-black text-xs text-muted uppercase tracking-widest mb-3">Lancer une partie</h2>

            @error('launch')
                <p class="mb-3 text-xs text-error font-medium">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('groups.launch', $group) }}" class="space-y-5">
                @csrf

                {{-- Thèmes --}}
                <div>
                    <label class="block text-sm font-semibold text-ink mb-2">Thèmes musicaux</label>
                    @php
                    $palette = ['#FEF3C7','#EDE9F8','#D1FAE5','#FEE2E2','#DBEAFE','#FCE7F3','#E0F2FE','#FEF9C3','#F0FDF4','#FFF7ED'];
                    @endphp
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @forelse ($themes as $theme)
                            @php $bg = $palette[$loop->index % count($palette)]; @endphp
                            <label
                                x-data="{ checked: {{ in_array($theme->id, old('theme_ids', [])) ? 'true' : 'false' }} }"
                                x-bind:class="checked ? 'ring-2 ring-primary scale-[1.02]' : 'ring-1 ring-transparent hover:ring-primary/30'"
                                class="group relative flex cursor-pointer flex-col items-center gap-1.5 rounded-2xl p-3 transition-all duration-200 select-none"
                                style="background: {{ $bg }}"
                            >
                                <input type="checkbox" name="theme_ids[]" value="{{ $theme->id }}" x-model="checked" class="sr-only" />
                                <span x-show="checked" x-cloak class="absolute top-1.5 right-1.5 w-5 h-5 bg-primary rounded-full flex items-center justify-center text-white text-[10px] font-black leading-none">✓</span>
                                <span class="text-3xl">{{ $theme->emoji }}</span>
                                <span class="text-center text-xs font-semibold text-ink leading-tight">{{ $theme->name }}</span>
                            </label>
                        @empty
                            <p class="col-span-full text-sm text-muted py-4 text-center">Aucun thème disponible.</p>
                        @endforelse
                    </div>
                    @error('theme_ids')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Réglages --}}
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-ink mb-1.5">Joueurs max</label>
                        <x-input type="number" name="max_players" value="{{ old('max_players', 8) }}" min="2" max="16" required />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-ink mb-1.5">Durée / manche (s)</label>
                        <x-input type="number" name="round_duration" value="{{ old('round_duration', 30) }}" min="15" max="60" required />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-ink mb-1.5">Nombre de manches</label>
                        <x-input type="number" name="total_rounds" value="{{ old('total_rounds', 10) }}" min="5" max="20" required />
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-ink mb-1.5">Tentatives / manche</label>
                        <select name="max_attempts" class="w-full rounded-xl border border-border bg-white px-4 py-2.5 text-sm text-ink transition focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary">
                            <option value="">Illimité</option>
                            @foreach ([1, 2, 3, 5] as $n)
                                <option value="{{ $n }}" @selected(old('max_attempts') == $n)>{{ $n }} tentative{{ $n > 1 ? 's' : '' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <x-btn type="submit" size="lg" class="w-full">Lancer la partie 🚀</x-btn>
            </form>
        </x-card>

        {{-- Parties récentes --}}
        @if ($recentRooms->isNotEmpty())
            <x-card padding="sm">
                <h2 class="font-display font-black text-xs text-muted uppercase tracking-widest mb-3">Parties récentes</h2>
                <div class="space-y-1.5">
                    @foreach ($recentRooms as $room)
                        <div class="flex items-center gap-3 rounded-xl bg-zinc-50 px-3 py-2 text-sm">
                            <span class="font-mono font-semibold tracking-wider text-ink">{{ $room->code }}</span>
                            <span class="flex-1 text-muted text-xs">{{ $room->created_at?->diffForHumans() }}</span>
                            <x-badge :variant="$room->status->value === 'finished' ? 'muted' : 'primary'">
                                {{ ucfirst($room->status->value) }}
                            </x-badge>
                        </div>
                    @endforeach
                </div>
            </x-card>
        @endif

    </div>
</x-layouts::app>
