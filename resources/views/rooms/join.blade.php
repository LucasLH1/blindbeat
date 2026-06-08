<x-layouts::app :title="__('Rejoindre une partie')">
    <div class="mx-auto max-w-sm space-y-6">

        <div>
            <h1 class="font-display text-3xl font-black text-ink">Rejoindre</h1>
            <p class="text-muted mt-1">Entre le code à 6 lettres pour rejoindre.</p>
        </div>

        <x-card>
            <form method="POST" action="{{ route('rooms.join.post') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-ink mb-1.5">Code de la room</label>
                    <input
                        name="code"
                        value="{{ old('code', request('code')) }}"
                        placeholder="ABC123"
                        maxlength="6"
                        autofocus
                        required
                        autocomplete="off"
                        class="w-full rounded-xl border border-border bg-white px-4 py-4 text-center font-mono text-2xl font-black tracking-[0.3em] uppercase text-ink transition focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary placeholder:tracking-normal placeholder:text-muted/60 placeholder:text-base"
                    />
                    @error('code')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>

                @guest
                <div>
                    <label class="block text-sm font-semibold text-ink mb-1.5">Ton pseudo</label>
                    <x-input name="guest_name" value="{{ old('guest_name') }}" placeholder="Ex : SuperMélomane" maxlength="20" required />
                    @error('guest_name')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>
                @endguest

                <x-btn type="submit" size="lg" class="w-full">
                    Rejoindre 🎮
                </x-btn>
            </form>
        </x-card>

        @auth
        <p class="text-center text-sm text-muted">
            Tu joues en tant que <span class="font-semibold text-ink">{{ auth()->user()?->name }}</span>.
        </p>
        @endauth

        <p class="text-center text-sm text-muted">
            Pas de room ?
            @auth
                <a href="{{ route('rooms.create') }}" class="font-semibold text-primary hover:underline">Créer une partie</a>
            @else
                <a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">Connecte-toi</a> pour en créer une.
            @endauth
        </p>

    </div>
</x-layouts::app>
