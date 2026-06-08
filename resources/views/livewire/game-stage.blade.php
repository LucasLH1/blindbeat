@script
<script>
    if (window.Echo) {
        window.Echo.join('room.{{ $code }}')
            .leaving(member => $wire.playerLeft(member.id));
    }

    setInterval(() => {
        fetch('/api/heartbeat', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
        });
    }, 30000);
</script>
@endscript

<div class="mx-auto max-w-2xl space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <h1 class="font-display text-2xl font-black text-ink">🎵 Blindtest</h1>
        @if ($currentRound)
            <x-badge variant="primary">
                Manche {{ $currentRound['round_number'] }} / {{ $room->total_rounds }}
            </x-badge>
        @endif
    </div>

    {{-- ================================================================== --}}
    {{-- STATE: waiting                                                       --}}
    {{-- ================================================================== --}}
    @if ($state === 'waiting')
        <x-card class="py-20 text-center space-y-3">
            <div class="text-5xl">⏳</div>
            <h2 class="font-display text-2xl font-black text-ink">La partie va commencer...</h2>
            <p class="text-muted">En attente du lancement de la première manche.</p>
        </x-card>

    {{-- ================================================================== --}}
    {{-- STATE: playing                                                       --}}
    {{-- ================================================================== --}}
    @elseif ($state === 'playing')
        <x-card
            wire:key="game-card-playing"
            x-data="(() => {
                let _iv = null;
                return {
                startedAt: {{ isset($currentRound['started_at_ms']) ? $currentRound['started_at_ms'] : 'null' }},
                durationMs: {{ $duration }} * 1000,
                progress: 100,
                timeLeft: {{ $duration }},
                answer: '',
                submitting: false,
                blocked: false,
                foundTitle: false,
                foundArtist: false,
                lastResult: null,
                error: null,
                volume: parseFloat(localStorage.getItem('blindtest_volume') ?? 1),
                volOpen: false,
                setVolume(v) {
                    this.volume = v;
                    localStorage.setItem('blindtest_volume', v);
                    if (this.$refs.audio) this.$refs.audio.volume = v;
                },
                start() {
                    const t0 = this.startedAt ?? Date.now();
                    this.startedAt = t0;
                    clearInterval(_iv);
                    _iv = setInterval(() => {
                        const elapsed = Date.now() - t0;
                        const remaining = Math.max(0, this.durationMs - elapsed);
                        this.progress = (remaining / this.durationMs) * 100;
                        this.timeLeft = Math.ceil(remaining / 1000);
                        if (remaining <= 0) clearInterval(_iv);
                    }, 100);
                },
                async submitAnswer() {
                    if (this.submitting || this.blocked || !this.answer.trim()) return;
                    this.submitting = true;
                    this.error = null;
                    try {
                        const res = await fetch('/api/answers', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                answer_text: this.answer,
                                room_code: '{{ $code }}',
                            }),
                        });
                        const data = await res.json();
                        this.submitting = false;
                        if (!res.ok) { this.error = data.error ?? 'Erreur.'; return; }
                        this.lastResult = data;
                        if (data.correct) {
                            if (data.answer_type === 'title')  this.foundTitle  = true;
                            if (data.answer_type === 'artist') this.foundArtist = true;
                            this.answer = '';
                        } else {
                            this.answer = '';
                        }
                        if ((this.foundTitle && this.foundArtist) || data.attempts_remaining === 0) {
                            this.blocked = true;
                        }
                    } catch (e) {
                        this.submitting = false;
                        this.error = 'Erreur réseau.';
                    }
                }
                }; // end return
            })()"
            x-init="start()"
            class="space-y-5"
        >
            {{-- Timer row --}}
            <div class="flex items-center justify-between">
                <p class="font-semibold text-ink text-sm">Trouve le titre et l'artiste !</p>
                <div class="flex items-baseline gap-1">
                    <span
                        class="text-3xl font-black tabular-nums transition-colors"
                        :class="timeLeft <= 5 ? 'text-error' : 'text-primary'"
                        x-text="timeLeft"
                    ></span>
                    <span class="text-sm text-muted">s</span>
                </div>
            </div>

            {{-- Progress bar --}}
            <div class="h-2 w-full rounded-full bg-zinc-100 overflow-hidden">
                <div
                    class="h-2 rounded-full transition-colors duration-300"
                    :class="timeLeft <= 5 ? 'bg-error' : 'bg-primary'"
                    :style="`width: ${progress}%`"
                ></div>
            </div>

            @if (!empty($currentRound['preview_url']))
                <audio
                    x-ref="audio"
                    autoplay
                    oncontextmenu="return false"
                    src="{{ $currentRound['preview_url'] }}"
                    x-init="
                        volume = parseFloat(localStorage.getItem('blindtest_volume') ?? 1);
                        $el.volume = volume;
                        $el._lastTime = 0;
                        $el.addEventListener('timeupdate', () => {
                            if ($el.currentTime < $el._lastTime) $el.currentTime = $el._lastTime;
                            $el._lastTime = $el.currentTime;
                        });
                    "
                ></audio>

                <div class="flex items-center justify-between">
                    <div class="flex items-end gap-1" style="height: 32px" aria-hidden="true">
                        <span class="sound-bar w-1.5 rounded-full bg-primary/60" style="height: 14px; display: inline-block; transform-origin: bottom; animation-delay: 0s"></span>
                        <span class="sound-bar w-1.5 rounded-full bg-primary/70" style="height: 24px; display: inline-block; transform-origin: bottom; animation-delay: 0.12s"></span>
                        <span class="sound-bar w-1.5 rounded-full bg-primary/80" style="height: 18px; display: inline-block; transform-origin: bottom; animation-delay: 0.24s"></span>
                        <span class="sound-bar w-1.5 rounded-full bg-primary/70" style="height: 28px; display: inline-block; transform-origin: bottom; animation-delay: 0.36s"></span>
                        <span class="sound-bar w-1.5 rounded-full bg-primary/60" style="height: 12px; display: inline-block; transform-origin: bottom; animation-delay: 0.48s"></span>
                    </div>

                    <div class="relative">
                        <button
                            @click="volOpen = !volOpen"
                            class="text-lg leading-none text-muted hover:text-ink transition-colors select-none"
                            title="Volume"
                            type="button"
                        >🔊</button>

                        <div
                            x-show="volOpen"
                            x-cloak
                            @click.outside="volOpen = false"
                            class="absolute bottom-8 right-0 z-20 bg-white rounded-2xl shadow-raised px-4 py-3 flex items-center gap-3"
                            style="min-width: 160px"
                        >
                            <span class="text-sm select-none">🔇</span>
                            <input
                                type="range"
                                min="0" max="1" step="0.05"
                                :value="volume"
                                @input="setVolume(parseFloat($event.target.value))"
                                class="vol-slider flex-1"
                            >
                            <span class="text-sm select-none">🔊</span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Titre / Artiste progress badges --}}
            <div class="flex gap-2">
                <span
                    class="text-xs px-2.5 py-1 rounded-full font-semibold transition-colors"
                    :class="foundTitle ? 'bg-green-100 text-green-700' : 'bg-zinc-100 text-zinc-400'"
                >🎵 Titre <span x-show="foundTitle">✓</span></span>
                <span
                    class="text-xs px-2.5 py-1 rounded-full font-semibold transition-colors"
                    :class="foundArtist ? 'bg-green-100 text-green-700' : 'bg-zinc-100 text-zinc-400'"
                >🎤 Artiste <span x-show="foundArtist">✓</span></span>
            </div>

            {{-- Formulaire de réponse --}}
            <template x-if="!blocked">
                <div class="space-y-3">
                    {{-- Partial success feedback --}}
                    <template x-if="lastResult?.correct">
                        <div class="rounded-xl bg-green-50 border border-green-100 px-4 py-2.5 flex items-center gap-2">
                            <span class="text-success font-bold text-sm">✓</span>
                            <span
                                class="text-sm font-semibold text-success flex-1"
                                x-text="lastResult.answer_type === 'title' ? 'Titre trouvé ! Maintenant l\'artiste...' : 'Artiste trouvé ! Maintenant le titre...'"
                            ></span>
                            <span class="text-xs font-bold text-success">+<span x-text="lastResult.points_earned"></span> pts</span>
                        </div>
                    </template>

                    <input
                        x-model="answer"
                        :placeholder="!foundTitle && !foundArtist ? 'Titre ou artiste...' : (foundTitle ? 'Artiste ?' : 'Titre ?')"
                        autofocus
                        x-bind:disabled="submitting"
                        @keydown.enter="submitAnswer()"
                        class="w-full rounded-xl border border-border bg-white px-4 py-3 text-sm text-ink placeholder:text-muted transition focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary disabled:opacity-50"
                    />

                    <template x-if="lastResult && !lastResult.correct">
                        <div class="rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-center">
                            <p class="font-semibold text-error text-sm">
                                ✗ Mauvaise réponse
                                <template x-if="lastResult.attempts_remaining !== null">
                                    <span> — encore <span x-text="lastResult.attempts_remaining"></span> tentative(s)</span>
                                </template>
                            </p>
                        </div>
                    </template>

                    <template x-if="error">
                        <p class="text-xs text-error font-medium" x-text="error"></p>
                    </template>

                    <button
                        @click="submitAnswer()"
                        x-bind:disabled="submitting || !answer.trim()"
                        class="w-full inline-flex items-center justify-center rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-50 disabled:cursor-not-allowed shadow-[0_4px_15px_rgba(124,92,191,0.4)]"
                    >
                        <span x-show="!submitting">Valider</span>
                        <span x-show="submitting" x-cloak>Envoi...</span>
                    </button>
                </div>
            </template>

            {{-- Résultat final (blocked) --}}
            <template x-if="blocked">
                <div
                    class="rounded-xl p-6 text-center border"
                    :class="(foundTitle && foundArtist) ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100'"
                >
                    <p
                        class="text-2xl font-black font-display"
                        :class="(foundTitle && foundArtist) ? 'text-success' : 'text-error'"
                        x-text="(foundTitle && foundArtist) ? '✓ Trouvé !' : '✗ Plus de tentatives'"
                    ></p>
                    <p class="mt-2 text-muted text-sm">En attente des autres joueurs...</p>
                </div>
            </template>
        </x-card>

    {{-- ================================================================== --}}
    {{-- STATE: revealed                                                      --}}
    {{-- ================================================================== --}}
    @elseif ($state === 'revealed')
        <x-card
            wire:key="game-card-revealed"
            x-data="(() => {
                let _iv  = null;
                let _ctx = null;
                return {
                    count: 5,
                    start() {
                        this.count = 5;
                        this.pulse();
                        this.beep(440, 0.08);
                        _iv = setInterval(() => {
                            this.count--;
                            this.pulse();
                            if (this.count <= 0) {
                                clearInterval(_iv);
                                this.beep(880, 0.15);
                            } else {
                                this.beep(440, 0.08);
                            }
                        }, 1000);
                    },
                    pulse() {
                        const el = this.$refs.digit;
                        if (!el) return;
                        el.classList.remove('countdown-pulse');
                        void el.offsetWidth;
                        el.classList.add('countdown-pulse');
                    },
                    beep(freq, dur) {
                        try {
                            if (!_ctx) _ctx = new (window.AudioContext || window.webkitAudioContext)();
                            const play = () => {
                                const osc = _ctx.createOscillator();
                                const gain = _ctx.createGain();
                                osc.connect(gain);
                                gain.connect(_ctx.destination);
                                osc.frequency.value = freq;
                                gain.gain.setValueAtTime(0.1, _ctx.currentTime);
                                gain.gain.exponentialRampToValueAtTime(0.001, _ctx.currentTime + dur);
                                osc.start();
                                osc.stop(_ctx.currentTime + dur);
                            };
                            _ctx.state === 'suspended'
                                ? _ctx.resume().then(play).catch(() => {})
                                : play();
                        } catch(e) {}
                    }
                };
            })()"
            x-init="start()"
            class="space-y-5 text-center"
        >
            <h2 class="font-display text-2xl font-black text-primary">🎵 La bonne réponse</h2>

            @if ($correctAnswer)
                @if ($correctAnswer['cover_url'])
                    <img
                        src="{{ $correctAnswer['cover_url'] }}"
                        alt="Pochette"
                        class="mx-auto h-40 w-40 rounded-2xl shadow-raised object-cover"
                    >
                @else
                    <div class="mx-auto flex h-40 w-40 items-center justify-center rounded-2xl bg-primary-light">
                        <span class="text-5xl">🎵</span>
                    </div>
                @endif

                <div>
                    <p class="text-2xl font-black font-display text-ink">{{ $correctAnswer['title'] }}</p>
                    <p class="text-base text-muted mt-0.5">{{ $correctAnswer['artist'] }}</p>
                </div>
            @endif

            {{-- Countdown interactif --}}
            <div class="flex flex-col items-center gap-1 py-2">
                <span
                    x-ref="digit"
                    x-text="count"
                    :class="{
                        'text-primary':     count >= 4,
                        'text-[#FFB347]':   count === 3 || count === 2,
                        'text-[#FF6B6B]':   count <= 1
                    }"
                    class="text-6xl font-display font-black tabular-nums leading-none transition-colors duration-300"
                ></span>
                <p class="text-sm text-muted">Prochaine manche...</p>
            </div>
        </x-card>

    {{-- ================================================================== --}}
    {{-- STATE: finished                                                      --}}
    {{-- ================================================================== --}}
    @elseif ($state === 'finished')
        <x-card class="space-y-6 text-center">
            <div>
                <div class="text-5xl mb-2">🏆</div>
                <h2 class="font-display text-3xl font-black text-ink">Fin de la partie !</h2>
            </div>

            @if (count($leaderboard) >= 1)
                <div class="flex items-end justify-center gap-3 py-4">
                    @foreach (array_slice($leaderboard, 0, 3) as $i => $entry)
                        <div class="flex flex-col items-center gap-2" style="order: {{ [1, 0, 2][$i] ?? $i }}">
                            <span class="text-2xl">{{ ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) }}</span>
                            <div
                                class="flex w-20 items-end justify-center rounded-t-2xl pt-2 pb-1"
                                style="height: {{ [110, 80, 64][$i] ?? 50 }}px; background: {{ ['#EDE9F8', '#F5F5F5', '#FEF3C7'][$i] ?? '#F5F5F5' }}"
                            >
                                <span class="text-xs font-bold text-ink text-center leading-tight px-1">{{ $entry['display_name'] ?? '' }}</span>
                            </div>
                            <span class="text-sm font-semibold text-muted">{{ $entry['score'] ?? 0 }} pts</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="space-y-2 text-left">
                @foreach ($leaderboard as $rank => $entry)
                    <div class="flex items-center gap-3 rounded-xl bg-zinc-50 px-4 py-3">
                        <span class="w-6 shrink-0 text-center text-sm font-bold text-muted">{{ $rank + 1 }}</span>
                        <x-avatar :name="$entry['display_name'] ?? ''" size="sm" />
                        <span class="flex-1 font-semibold text-ink text-sm">{{ $entry['display_name'] ?? '' }}</span>
                        <span class="font-black text-primary text-sm">{{ $entry['score'] ?? 0 }} pts</span>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-3">
                <button
                    wire:click="replayGame"
                    wire:loading.attr="disabled"
                    class="w-full inline-flex items-center justify-center rounded-xl bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-50 animate-pulse-cta"
                >
                    <span wire:loading.remove wire:target="replayGame">🔄 Rejouer</span>
                    <span wire:loading wire:target="replayGame">Création...</span>
                </button>
                <x-btn :href="route('home')" size="lg" class="w-full" variant="ghost">
                    Retour à l'accueil
                </x-btn>
            </div>
        </x-card>
    @endif

    {{-- ================================================================== --}}
    {{-- SCOREBOARD unifié (playing + revealed)                               --}}
    {{-- ================================================================== --}}
    @if (in_array($state, ['playing', 'revealed']))
        <x-card padding="sm">
            <h3 class="font-display font-black text-xs text-muted uppercase tracking-widest mb-3">Scores</h3>

            @php $roundResultsById = collect($roundAnswers)->keyBy('game_player_id'); @endphp

            <div class="space-y-1.5">
                @foreach ($players as $i => $player)
                    @php
                        $rankingPos   = array_search($player->id, $roundRanking);
                        $progress     = $playerProgress[$player->id] ?? ['found_title' => false, 'found_artist' => false];
                        $roundResult  = $roundResultsById[$player->id] ?? null;
                        $foundTitle   = $state === 'revealed' ? ($roundResult['found_title']  ?? false) : ($progress['found_title']  ?? false);
                        $foundArtist  = $state === 'revealed' ? ($roundResult['found_artist'] ?? false) : ($progress['found_artist'] ?? false);
                        $wrongAnswer  = $lastWrongAnswer[$player->id] ?? null;
                        $ptsThisRound = $roundResult['points_this_round'] ?? 0;
                    @endphp
                    <div wire:key="scoreboard-{{ $player->id }}"
                        class="flex items-center gap-3 rounded-xl px-3 py-2.5
                        {{ $i === 0 ? 'bg-primary-light' : 'bg-zinc-50' }}"
                    >
                        {{-- Médaille ou rang numérique --}}
                        <span class="w-6 shrink-0 text-center text-sm">
                            @if ($rankingPos === 0) 🥇
                            @elseif ($rankingPos === 1) 🥈
                            @elseif ($rankingPos === 2) 🥉
                            @else <span class="text-xs font-bold text-muted">{{ $i + 1 }}</span>
                            @endif
                        </span>

                        <x-avatar :name="$player->displayName()" size="sm" />

                        {{-- Nom + dernière mauvaise réponse (playing only, si pas encore trouvé les deux) --}}
                        <div class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold text-ink truncate">{{ $player->displayName() }}</span>
                            @if ($state === 'playing' && !($foundTitle && $foundArtist) && $wrongAnswer)
                                <span class="block text-xs italic text-muted/60 truncate">« {{ $wrongAnswer }} »</span>
                            @endif
                        </div>

                        {{-- Badges 🎵🎤 --}}
                        <div class="flex gap-1 shrink-0">
                            <span class="text-xs px-1.5 py-0.5 rounded-full {{ $foundTitle  ? 'bg-green-100 text-green-700' : 'bg-zinc-100 text-zinc-400' }}">🎵</span>
                            <span class="text-xs px-1.5 py-0.5 rounded-full {{ $foundArtist ? 'bg-green-100 text-green-700' : 'bg-zinc-100 text-zinc-400' }}">🎤</span>
                        </div>

                        {{-- Score + +X pts (revealed). Le numéro est du Blade pur (morphé par
                             Livewire). L'animation "+X pts" vit dans un îlot wire:ignore isolé :
                             Alpine y est seul maître, Livewire n'y touche jamais au morph. --}}
                        <div class="relative flex flex-col items-end shrink-0">
                            <span class="font-black text-base {{ $i === 0 ? 'text-primary' : 'text-zinc-600' }}">{{ $player->score }}</span>
                            @if ($state === 'revealed' && $ptsThisRound > 0)
                                <span class="text-xs font-bold text-success leading-none">+{{ $ptsThisRound }}</span>
                            @endif
                            <div
                                wire:ignore
                                x-data="{ bonus: null, t: null }"
                                x-on:player-scored.window="
                                    if ($event.detail.playerId === '{{ $player->id }}') {
                                        bonus = $event.detail.points;
                                        clearTimeout(t);
                                        t = setTimeout(() => bonus = null, 1400);
                                    }
                                "
                                class="absolute -top-5 left-0 pointer-events-none"
                            >
                                <template x-if="bonus !== null">
                                    <span class="animate-float-up text-xs font-black text-success whitespace-nowrap" x-text="'+' + bonus"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

</div>
