<x-layouts::app :title="__('Rejoindre une partie')">
    <div class="mx-auto max-w-sm space-y-6 py-10">

        <div class="space-y-1">
            <flux:heading size="xl">Rejoindre une partie</flux:heading>
            <flux:text class="text-zinc-400">Entre le code de la room pour rejoindre.</flux:text>
        </div>

        <flux:card class="space-y-5">
            <form method="POST" action="{{ route('rooms.join.post') }}" class="space-y-5">
                @csrf

                <flux:field>
                    <flux:label>Code de la room</flux:label>
                    <flux:input
                        name="code"
                        value="{{ old('code', request('code')) }}"
                        placeholder="ABC123"
                        maxlength="6"
                        class="font-mono tracking-widest uppercase text-center text-lg"
                        autofocus
                        required
                    />
                    @error('code')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                @guest
                    <flux:field>
                        <flux:label>Ton pseudo</flux:label>
                        <flux:input
                            name="guest_name"
                            value="{{ old('guest_name') }}"
                            placeholder="Ex : SuperMélomane"
                            maxlength="20"
                            required
                        />
                        @error('guest_name')
                            <flux:error>{{ $message }}</flux:error>
                        @enderror
                    </flux:field>
                @endguest

                <flux:button type="submit" variant="primary" class="w-full">
                    Rejoindre
                </flux:button>
            </form>
        </flux:card>

        @auth
            <flux:text class="text-center text-sm text-zinc-400">
                Tu joues en tant que <span class="font-medium text-zinc-600">{{ auth()->user()?->name }}</span>.
            </flux:text>
        @endauth

        <flux:text class="text-center text-sm text-zinc-400">
            Pas encore de room ?
            @auth
                <a href="{{ route('rooms.create') }}" class="font-medium text-violet-600 hover:underline">Créer une partie</a>
            @else
                <a href="{{ route('login') }}" class="font-medium text-violet-600 hover:underline">Connecte-toi</a> pour en créer une.
            @endauth
        </flux:text>

    </div>
</x-layouts::app>
