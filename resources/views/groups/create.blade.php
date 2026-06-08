<x-layouts::app :title="__('Créer un groupe')">
    <div class="mx-auto max-w-lg space-y-6">

        <div>
            <h1 class="font-display text-3xl font-black text-ink">Créer un groupe</h1>
            <p class="text-muted mt-1">Invitez vos amis avec le code généré.</p>
        </div>

        <x-card>
            <form method="POST" action="{{ route('groups.store') }}" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm font-semibold text-ink mb-1.5">Nom du groupe</label>
                    <x-input name="name" value="{{ old('name') }}" maxlength="50" placeholder="Ex : Les Mélomanes" required />
                    @error('name')
                        <p class="mt-1.5 text-xs text-error font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center gap-3">
                    <x-btn type="submit" size="lg" class="flex-1">Créer le groupe 🎉</x-btn>
                    <x-btn :href="route('groups.index')" variant="ghost">Annuler</x-btn>
                </div>
            </form>
        </x-card>

    </div>
</x-layouts::app>
