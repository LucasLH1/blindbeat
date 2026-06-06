@script
<script>
    if (window.Echo) {
        window.Echo.join('room.{{ $code }}')
            .leaving(member => $wire.playerLeft(member.id));
    }
</script>
@endscript

<div class="mx-auto max-w-2xl space-y-6 py-8">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ $room->playlist?->name }}</flux:heading>
        @if ($currentRound)
            <flux:badge color="violet" size="lg">
                Manche {{ $currentRound['round_number'] }} / {{ $room->total_rounds }}
            </flux:badge>
        @endif
    </div>

    {{-- ------------------------------------------------------------------ --}}
    {{-- STATE: waiting                                                       --}}
    {{-- ------------------------------------------------------------------ --}}
    @if ($state === 'waiting')
        <flux:card class="py-16 text-center space-y-4">
            <flux:heading size="xl" class="text-zinc-500">⏳ La partie va commencer...</flux:heading>
            <flux:text>En attente du lancement de la première manche.</flux:text>
        </flux:card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- STATE: playing                                                       --}}
    {{-- ------------------------------------------------------------------ --}}
    @elseif ($state === 'playing')
        <flux:card
            x-data="{
                timeLeft: {{ $duration }},
                interval: null,
                answer: '',
                submitting: false,
                blocked: false,
                lastResult: null,
                error: null,
                start() {
                    clearInterval(this.interval);
                    this.interval = setInterval(() => {
                        if (this.timeLeft > 0) this.timeLeft--;
                    }, 1000);
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
                        if (data.correct || data.attempts_remaining === 0) {
                            this.blocked = true;
                        } else {
                            this.answer = '';
                        }
                    } catch (e) {
                        this.submitting = false;
                        this.error = 'Erreur réseau.';
                    }
                }
            }"
            x-init="start()"
            class="space-y-6"
        >
            {{-- Timer --}}
            <div class="flex items-center justify-between">
                <flux:text class="font-medium">Trouve l'artiste ou le titre !</flux:text>
                <div class="flex items-center gap-2">
                    <div
                        class="text-3xl font-bold tabular-nums"
                        :class="timeLeft <= 5 ? 'text-red-500' : 'text-violet-600'"
                        x-text="timeLeft"
                    ></div>
                    <flux:text class="text-zinc-400">s</flux:text>
                </div>
            </div>

            {{-- Progress bar --}}
            <div class="h-2 w-full rounded-full bg-zinc-100">
                <div
                    class="h-2 rounded-full bg-violet-400 transition-all duration-1000"
                    :style="`width: ${(timeLeft / {{ $duration }}) * 100}%`"
                ></div>
            </div>

            {{-- Audio player --}}
            @if (!empty($currentRound['preview_url']))
                <audio
                    autoplay
                    controls
                    class="w-full"
                    src="{{ $currentRound['preview_url'] }}"
                ></audio>
            @endif

            {{-- Answer form --}}
            <template x-if="!blocked">
                <div class="space-y-3">
                    <flux:input
                        x-model="answer"
                        placeholder="Titre ou artiste..."
                        autofocus
                        x-bind:disabled="submitting"
                        @keydown.enter="submitAnswer()"
                    />

                    {{-- Wrong answer feedback with retries --}}
                    <template x-if="lastResult && !lastResult.correct">
                        <div class="rounded-lg bg-red-50 px-4 py-3 text-center">
                            <p class="font-medium text-red-600">
                                ✗ Mauvaise réponse
                                <template x-if="lastResult.attempts_remaining !== null">
                                    <span> — encore <span x-text="lastResult.attempts_remaining"></span> tentative(s)</span>
                                </template>
                            </p>
                        </div>
                    </template>

                    <template x-if="error">
                        <flux:text class="text-red-500" x-text="error"></flux:text>
                    </template>

                    <flux:button
                        variant="primary"
                        class="w-full"
                        @click="submitAnswer()"
                        x-bind:disabled="submitting || !answer.trim()"
                    >
                        <span x-show="!submitting">Valider</span>
                        <span x-show="submitting" x-cloak>Envoi...</span>
                    </flux:button>
                </div>
            </template>

            {{-- Final result (correct or exhausted) --}}
            <template x-if="blocked">
                <div
                    class="rounded-xl p-6 text-center"
                    :class="lastResult?.correct ? 'bg-emerald-50' : 'bg-red-50'"
                >
                    <p
                        class="text-2xl font-bold"
                        :class="lastResult?.correct ? 'text-emerald-600' : 'text-red-500'"
                        x-text="lastResult?.correct ? '✓ Bonne réponse !' : '✗ Plus de tentatives'"
                    ></p>
                    <p x-show="lastResult?.correct" class="mt-1 text-emerald-700 font-medium">
                        + <span x-text="lastResult?.points_earned"></span> points
                    </p>
                    <p class="mt-2 text-zinc-500 text-sm">En attente des autres joueurs...</p>
                </div>
            </template>
        </flux:card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- STATE: revealed                                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @elseif ($state === 'revealed')
        <flux:card class="space-y-6 text-center">
            <flux:heading size="xl" class="text-violet-600">🎵 La bonne réponse</flux:heading>

            @if ($correctAnswer)
                @if ($correctAnswer['cover_url'])
                    <img
                        src="{{ $correctAnswer['cover_url'] }}"
                        alt="Pochette"
                        class="mx-auto h-40 w-40 rounded-2xl shadow-lg"
                    >
                @else
                    <div class="mx-auto flex h-40 w-40 items-center justify-center rounded-2xl bg-violet-100">
                        <flux:icon.musical-note class="size-16 text-violet-400" />
                    </div>
                @endif

                <div>
                    <p class="text-2xl font-bold text-zinc-800">{{ $correctAnswer['title'] }}</p>
                    <p class="text-lg text-zinc-500">{{ $correctAnswer['artist'] }}</p>
                </div>
            @endif

            <flux:text class="text-zinc-400">Prochaine manche dans quelques secondes...</flux:text>
        </flux:card>

    {{-- ------------------------------------------------------------------ --}}
    {{-- STATE: finished                                                      --}}
    {{-- ------------------------------------------------------------------ --}}
    @elseif ($state === 'finished')
        <flux:card class="space-y-6 text-center">
            <flux:heading size="xl">🏆 Fin de la partie</flux:heading>

            {{-- Podium top 3 --}}
            @if (count($leaderboard) >= 1)
                <div class="flex items-end justify-center gap-4">
                    @foreach (array_slice($leaderboard, 0, 3) as $i => $entry)
                        <div class="flex flex-col items-center gap-2"
                            style="order: {{ [1, 0, 2][$i] ?? $i }}">
                            <flux:badge
                                color="{{ ['gold' => 'yellow', 'silver' => 'zinc', 'bronze' => 'orange'][$i] ?? 'zinc' }}"
                                size="sm"
                            >
                                {{ ['🥇', '🥈', '🥉'][$i] ?? ($i + 1) }}
                            </flux:badge>
                            <div
                                class="flex w-20 items-end justify-center rounded-t-xl bg-violet-100 pt-2 font-bold text-violet-700"
                                style="height: {{ [120, 90, 70][$i] ?? 60 }}px"
                            >
                                {{ $entry['display_name'] }}
                            </div>
                            <span class="text-sm font-semibold text-zinc-600">{{ $entry['score'] }} pts</span>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Full leaderboard --}}
            <div class="space-y-2 text-left">
                @foreach ($leaderboard as $rank => $entry)
                    <div class="flex items-center gap-3 rounded-xl bg-zinc-50 px-4 py-3">
                        <span class="w-6 text-center font-bold text-zinc-400">{{ $rank + 1 }}</span>
                        <flux:avatar name="{{ $entry['display_name'] }}" class="shrink-0" />
                        <span class="flex-1 font-medium">{{ $entry['display_name'] }}</span>
                        <span class="font-bold text-violet-600">{{ $entry['score'] }} pts</span>
                    </div>
                @endforeach
            </div>

            <flux:button :href="route('home')" variant="primary">Retour à l'accueil</flux:button>
        </flux:card>
    @endif

    {{-- Scores sidebar --}}
    @if (in_array($state, ['playing', 'revealed']))
        <flux:card class="space-y-3">
            <flux:heading size="sm">Scores</flux:heading>
            @foreach ($players as $player)
                <div class="flex items-center gap-3">
                    <flux:avatar name="{{ $player->displayName() }}" class="shrink-0 size-7" />
                    <span class="flex-1 text-sm font-medium">{{ $player->displayName() }}</span>
                    <span class="font-bold text-violet-600">{{ $player->score }}</span>
                </div>
            @endforeach
        </flux:card>
    @endif

</div>
