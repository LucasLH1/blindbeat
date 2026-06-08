<x-layouts::app :title="__('Créer une room')">
    <div class="mx-auto max-w-lg space-y-6">

        <div>
            <h1 class="font-display text-3xl font-black text-ink">Créer une room</h1>
            <p class="text-muted mt-1">Configure ta partie et choisis les thèmes.</p>
        </div>

        <x-card>
            <form method="POST" action="{{ route('rooms.store') }}" class="space-y-6">
                @csrf

                @guest
                <div>
                    <label class="block text-sm font-semibold text-ink mb-1.5">Votre pseudo</label>
                    <x-input name="guest_name" value="{{ old('guest_name') }}" maxlength="20" placeholder="Ex : Maestro" required />
                    @error('guest_name')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>
                @endguest

                {{-- Theme grid --}}
                <div>
                    <label class="block text-sm font-semibold text-ink mb-2">Thèmes musicaux</label>
                    @php
                    $palette = [
                        '#FEF3C7', // amber
                        '#EDE9F8', // violet
                        '#D1FAE5', // vert
                        '#FEE2E2', // rouge
                        '#DBEAFE', // bleu
                        '#FCE7F3', // rose
                        '#E0F2FE', // ciel
                        '#FEF9C3', // jaune
                        '#F0FDF4', // menthe
                        '#FFF7ED', // pêche
                    ];
                    @endphp
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @forelse ($themes as $theme)
                            @php $bg = $palette[$loop->index % count($palette)]; @endphp
                            <label
                                x-data="{ checked: {{ in_array($theme->id, old('theme_ids', [])) ? 'true' : 'false' }} }"
                                x-bind:class="checked
                                    ? 'ring-2 ring-primary scale-[1.02]'
                                    : 'ring-1 ring-transparent hover:ring-primary/30 hover:scale-[1.01]'"
                                class="group relative flex cursor-pointer flex-col items-center gap-2 rounded-2xl p-4 transition-all duration-200 select-none"
                                style="background: {{ $bg }}; touch-action: manipulation"
                            >
                                <input
                                    type="checkbox"
                                    name="theme_ids[]"
                                    value="{{ $theme->id }}"
                                    x-model="checked"
                                    class="sr-only"
                                />
                                {{-- Checkmark overlay --}}
                                <span
                                    x-show="checked"
                                    x-cloak
                                    class="pointer-events-none absolute top-1.5 right-1.5 w-5 h-5 bg-primary rounded-full flex items-center justify-center text-white text-[10px] font-black leading-none"
                                >✓</span>
                                {{-- Emoji avec rotation au survol --}}
                                <span class="inline-block text-4xl transition-transform duration-200 group-hover:rotate-[10deg]">{{ $theme->emoji }}</span>
                                <span class="text-center text-xs font-semibold text-ink leading-tight">{{ $theme->name }}</span>
                                <span class="text-xs text-muted">{{ $theme->tracks_count }} titres</span>
                            </label>
                        @empty
                            <p class="col-span-full text-sm text-muted py-4 text-center">
                                Aucun thème — lancez <code class="font-mono bg-zinc-100 px-1 rounded">php artisan themes:sync</code>
                            </p>
                        @endforelse
                    </div>
                    @error('theme_ids')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Settings grid --}}
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-ink mb-1.5">Joueurs max</label>
                        <x-input type="number" name="max_players" value="{{ old('max_players', 8) }}" min="2" max="16" required />
                        @error('max_players')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-ink mb-1.5">Durée par manche (s)</label>
                        <x-input type="number" name="round_duration" value="{{ old('round_duration', 30) }}" min="15" max="60" required />
                        @error('round_duration')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-ink mb-1.5">Nombre de manches</label>
                        <x-input type="number" name="total_rounds" value="{{ old('total_rounds', 10) }}" min="5" max="20" required />
                        @error('total_rounds')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-ink mb-1.5">Tentatives par manche</label>
                        <select
                            name="max_attempts"
                            class="w-full rounded-xl border border-border bg-white px-4 py-2.5 text-sm text-ink transition focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary"
                        >
                            <option value="">Illimité</option>
                            @foreach ([1, 2, 3, 5] as $n)
                                <option value="{{ $n }}" @selected(old('max_attempts') == $n)>
                                    {{ $n }} tentative{{ $n > 1 ? 's' : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('max_attempts')
                            <p class="mt-1 text-xs text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Option top_only --}}
                <label
                    x-data="{ checked: {{ old('top_only') ? 'true' : 'false' }} }"
                    x-bind:class="checked ? 'ring-2 ring-primary bg-primary-light' : 'ring-1 ring-border bg-white hover:bg-zinc-50'"
                    class="flex items-center gap-3 cursor-pointer rounded-xl px-4 py-3 transition-all duration-150 select-none"
                    style="touch-action: manipulation"
                >
                    <input type="hidden" name="top_only" value="0" />
                    <input
                        type="checkbox"
                        name="top_only"
                        value="1"
                        x-model="checked"
                        class="sr-only"
                    />
                    <span
                        x-bind:class="checked ? 'bg-primary border-primary' : 'bg-white border-border'"
                        class="flex-shrink-0 w-5 h-5 rounded-md border-2 flex items-center justify-center transition-colors duration-150"
                    >
                        <svg x-show="checked" x-cloak class="w-3 h-3 text-white" fill="none" viewBox="0 0 12 12">
                            <path d="M2 6l3 3 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-semibold text-ink">🔥 Top du moment uniquement</span>
                        <p class="text-xs text-muted mt-0.5">Uniquement les tracks les plus connues (rank &gt; 500&nbsp;000)</p>
                    </div>
                </label>

                <x-btn type="submit" size="lg" class="w-full">
                    Créer la room 🚀
                </x-btn>
            </form>
        </x-card>

    </div>
</x-layouts::app>
