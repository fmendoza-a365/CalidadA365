<section class="h-full">
    <form method="post" action="{{ route('password.update') }}" class="flex flex-col h-full">
        @csrf
        @method('put')

        <div class="space-y-4 mb-4">
            <div class="form-group">
                <label for="current_password" class="form-label">Contraseña Actual</label>
                <input type="password" name="current_password" id="current_password" 
                    class="form-input w-full" autocomplete="current-password">
                <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="password" class="form-label">Nueva Contraseña</label>
                <input type="password" name="password" id="password" 
                    class="form-input w-full" autocomplete="new-password">
                <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1" />
            </div>

            <div class="form-group">
                <label for="password_confirmation" class="form-label">Confirmar Contraseña</label>
                <input type="password" name="password_confirmation" id="password_confirmation" 
                    class="form-input w-full" autocomplete="new-password">
                <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1" />
            </div>
        </div>

        <div class="mt-auto flex items-center justify-end gap-4">
            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-emerald-600 dark:text-emerald-400">
                    Guardado.
                </p>
            @endif

            <button type="submit" class="btn-primary btn-md w-full sm:w-auto">Actualizar Contraseña</button>
        </div>
    </form>
</section>
