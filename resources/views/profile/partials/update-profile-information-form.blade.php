<section class="h-full">
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="flex flex-col h-full">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-4 mb-4">
            <div class="form-group">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" 
                    class="form-input w-full" required autofocus>
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="email" class="form-label">Correo Electrónico</label>
                <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" 
                    class="form-input w-full" required>
                <x-input-error :messages="$errors->get('email')" class="mt-1" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="mt-2">
                        <p class="text-sm text-amber-600 dark:text-amber-400">
                            Tu dirección de correo electrónico no está verificada.
                            <button form="send-verification" class="underline text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                Haz clic aquí para reenviar el correo de verificación.
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm text-emerald-600 dark:text-emerald-400">
                                Se ha enviado un nuevo enlace de verificación a tu correo electrónico.
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="mt-auto flex items-center justify-end gap-4">
            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-emerald-600 dark:text-emerald-400">
                    Guardado.
                </p>
            @endif

            <button type="submit" class="btn-primary btn-md w-full sm:w-auto">Guardar Cambios</button>
        </div>
    </form>
</section>
