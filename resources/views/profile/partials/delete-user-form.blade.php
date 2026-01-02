<section class="space-y-6">
    <div class="bg-rose-50 dark:bg-rose-900/10 border border-rose-100 dark:border-rose-800 rounded-xl p-6">
        <h3 class="text-lg font-medium text-rose-900 dark:text-rose-100">Zona de Peligro</h3>
        <p class="mt-1 text-sm text-rose-600 dark:text-rose-300">
            Una vez eliminada tu cuenta, todos sus recursos y datos serán eliminados permanentemente.
        </p>
        <div class="mt-4">
            <button type="button" class="btn-danger btn-md" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
                Eliminar Cuenta
            </button>
        </div>
    </div>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6 relative">
            @csrf
            @method('delete')

            <div class="text-center mb-6">
                <!-- Icono de Peligro Consistente -->
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full mb-4 bg-rose-50 text-rose-500 dark:bg-rose-900/20 dark:text-rose-400">
                    <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>

                <h2 class="text-xl font-bold text-gray-900 dark:text-white font-inter">
                    ¿Eliminar cuenta permanentemente?
                </h2>

                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
                    Esta acción no se puede deshacer. Por favor, ingresa tu contraseña para confirmar.
                </p>
            </div>

            <div class="mt-6 max-w-sm mx-auto">
                <label for="password" class="sr-only">Contraseña</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <input type="password" 
                        name="password" 
                        id="password" 
                        class="form-input pl-10 block w-full rounded-lg border-gray-300 dark:border-gray-600 focus:border-rose-500 focus:ring-rose-500 sm:text-sm dark:bg-gray-700" 
                        placeholder="Tu contraseña actual"
                    >
                </div>
                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-center" />
            </div>

            <div class="mt-8 grid grid-cols-2 gap-3 max-w-sm mx-auto">
                <button type="button" class="btn-secondary btn-md w-full justify-center" x-on:click="$dispatch('close')">Cancelar</button>
                <button type="submit" class="btn-danger btn-md w-full justify-center">Sí, eliminar cuenta</button>
            </div>
        </form>
    </x-modal>
</section>
