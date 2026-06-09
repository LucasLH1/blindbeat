<x-layouts::app :title="'Connexion'">
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
                <h1 class="font-display text-2xl font-black text-ink">Connexion</h1>
                <p class="text-muted text-sm mt-1">Entre tes identifiants pour te connecter.</p>
            </div>

            @if (session('status'))
                <div class="mb-5 rounded-xl border border-success/30 bg-success/10 px-4 py-2.5 text-center text-sm font-medium text-success">
                    {{ session('status') }}
                </div>
            @endif

            <x-passkey-verify />

            <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-semibold text-ink mb-1.5">Adresse e-mail</label>
                    <x-input id="email" name="email" type="email" value="{{ old('email') }}"
                        required autofocus autocomplete="email" placeholder="email@exemple.com" />
                    @error('email')
                        <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-sm font-semibold text-ink">Mot de passe</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" wire:navigate class="text-xs font-semibold text-primary hover:underline">
                                Mot de passe oublié ?
                            </a>
                        @endif
                    </div>
                    <x-input id="password" name="password" type="password"
                        required autocomplete="current-password" placeholder="Mot de passe" />
                    @error('password')
                        <p class="mt-1.5 text-xs font-medium text-error">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-muted select-none">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}
                        class="rounded border-border text-primary focus:ring-2 focus:ring-primary/30">
                    Se souvenir de moi
                </label>

                <x-btn type="submit" size="lg" class="w-full" data-test="login-button">
                    Se connecter
                </x-btn>
            </form>

            <p class="text-center text-sm text-muted mt-6">
                Pas encore de compte ?
                <a href="{{ route('register') }}" wire:navigate class="font-semibold text-primary hover:underline">Inscris-toi</a>
            </p>
        </x-card>
    </div>
</x-layouts::app>
