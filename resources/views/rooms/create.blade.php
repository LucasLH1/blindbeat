<x-layouts::app :title="__('Créer une room')">
    <div class="mx-auto max-w-lg space-y-6 py-10">

        <flux:heading size="xl">Créer une room</flux:heading>

        <flux:card class="space-y-6">
            <form method="POST" action="{{ route('rooms.store') }}" class="space-y-5">
                @csrf

                @guest
                <flux:field>
                    <flux:label>Votre pseudo</flux:label>
                    <flux:input
                        type="text"
                        name="guest_name"
                        value="{{ old('guest_name') }}"
                        maxlength="20"
                        required
                        placeholder="Ex : Maestro"
                    />
                    @error('guest_name')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>
                @endguest

                <flux:field>
                    <flux:label>Playlist</flux:label>
                    <flux:select name="playlist_id" required>
                        <flux:select.option value="" disabled :selected="!old('playlist_id')">Choisir une playlist...</flux:select.option>
                        @foreach ($playlists as $playlist)
                            <flux:select.option value="{{ $playlist->id }}" :selected="old('playlist_id') == $playlist->id">
                                {{ $playlist->name }} ({{ $playlist->tracks->count() }} titres)
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('playlist_id')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Nombre de joueurs max</flux:label>
                    <flux:input
                        type="number"
                        name="max_players"
                        value="{{ old('max_players', 8) }}"
                        min="2"
                        max="16"
                        required
                    />
                    @error('max_players')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Durée par manche (secondes)</flux:label>
                    <flux:input
                        type="number"
                        name="round_duration"
                        value="{{ old('round_duration', 30) }}"
                        min="15"
                        max="60"
                        required
                    />
                    @error('round_duration')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Nombre de manches</flux:label>
                    <flux:input
                        type="number"
                        name="total_rounds"
                        value="{{ old('total_rounds', 10) }}"
                        min="5"
                        max="20"
                        required
                    />
                    @error('total_rounds')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:field>
                    <flux:label>Tentatives par manche</flux:label>
                    <flux:select name="max_attempts">
                        <flux:select.option value="">Illimité</flux:select.option>
                        @foreach ([1, 2, 3, 5] as $n)
                            <flux:select.option value="{{ $n }}" :selected="old('max_attempts') == $n">
                                {{ $n }} tentative{{ $n > 1 ? 's' : '' }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    @error('max_attempts')
                        <flux:error>{{ $message }}</flux:error>
                    @enderror
                </flux:field>

                <flux:button type="submit" variant="primary" class="w-full">
                    Créer la room
                </flux:button>
            </form>
        </flux:card>

    </div>
</x-layouts::app>
