<x-guest-layout>
    <div class="min-h-screen flex items-center justify-center p-8 bg-gray-50 dark:bg-gray-950">
        <div class="w-full max-w-md space-y-8">
            <!-- Logo -->
            <div class="text-center">
                <a href="/" class="inline-flex items-center gap-2">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 flex items-center justify-center">
                        <svg class="w-7 h-7 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </a>
                <h2 class="mt-6 text-2xl font-bold text-gray-900 dark:text-white">¿Olvidaste tu contraseña?</h2>
                <p class="mt-2 text-gray-600 dark:text-gray-400">
                    No hay problema. Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
                </p>
            </div>

            <!-- Session Status -->
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}" class="space-y-6">
                @csrf

                <div class="form-group">
                    <label for="email" class="form-label">Correo Electrónico</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" 
                        class="form-input" placeholder="tu@email.com" required autofocus>
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>

                <button type="submit" class="btn-primary w-full btn-lg">
                    Enviar Enlace de Recuperación
                </button>

                <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                    <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                        ← Volver al inicio de sesión
                    </a>
                </p>
            </form>
        </div>
    </div>
</x-guest-layout>
