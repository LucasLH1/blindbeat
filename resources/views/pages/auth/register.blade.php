<x-layouts::app :title="'Inscription'">
    <div class="mx-auto max-w-md">
        <x-card padding="lg">
            {{-- Logo Frenzy --}}
            <div class="flex flex-col items-center text-center mb-6">
                <a href="{{ route('home') }}" class="mb-4 select-none" aria-label="Accueil">
                    <svg width="52" height="52" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="16" cy="16" r="16" fill="#7c5cbf"/>
                        <rect x="7" y="14" width="3" height="8" rx="1.5" fill="white" opacity="0.5"/>
                        <rect x="11.5" y="10" width="3" height="16" rx="1.5" fill="white" opacity="0.85"/>
                        <rect x="16" y="7" width="3" height="22" rx="1.5" fill="white"/>
                        <rect x="20.5" y="10" width="3" height="16" rx="1.5" fill="white" opacity="0.85"/>
                        <circle cx="27" cy="6" r="4" fill="#a8e6cf"/>
                        <circle cx="27" cy="6" r="2" fill="#4caf82"/>
                    </svg>
                </a>
                <h1 class="font-display text-2xl font-black text-ink">Créer un compte</h1>
                <p class="text-muted text-sm mt-1">Rejoins Frenzy en quelques secondes.</p>
            </div>

            @if (session('status'))
                <div class="mb-5 rounded-xl border border-success/30 bg-success/10 px-4 py-2.5 text-center text-sm font-medium text-success">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-semibold text-ink mb-1.5">Nom</label>
                    <x-input id="name" name="name" type="text" value="{{ old('name') }}"
                        required autofocus autocomplete="name" placeholder="Ton nom complet" />
                    @error('name')
                        <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-semibold text-ink mb-1.5">Adresse e-mail</label>
                    <x-input id="email" name="email" type="email" value="{{ old('email') }}"
                        required autocomplete="email" placeholder="email@exemple.com" />
                    @error('email')
                        <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-semibold text-ink mb-1.5">Mot de passe</label>
                    <x-input id="password" name="password" type="password"
                        required autocomplete="new-password" placeholder="Mot de passe"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />
                    @error('password')
                        <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-semibold text-ink mb-1.5">Confirmer le mot de passe</label>
                    <x-input id="password_confirmation" name="password_confirmation" type="password"
                        required autocomplete="new-password" placeholder="Confirme ton mot de passe"
                        passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}" />
                    @error('password_confirmation')
                        <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                    @enderror
                </div>

                <x-btn type="submit" size="lg" class="w-full" data-test="register-user-button">
                    Créer mon compte
                </x-btn>
            </form>

            <p class="text-center text-sm text-muted mt-6">
                Déjà un compte ?
                <a href="{{ route('login') }}" wire:navigate class="font-semibold text-primary hover:underline">Connecte-toi</a>
            </p>
        </x-card>
    </div>
</x-layouts::app>
