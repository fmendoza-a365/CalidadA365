<x-guest-layout>
    <div class="min-h-screen flex">
        <!-- Left Panel - Brand -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-indigo-600 via-purple-600 to-pink-500 p-12 flex-col justify-between">
            <div>
                <a href="/" class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                        <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="text-2xl font-bold text-white">QualityAI</span>
                </a>
            </div>
            
            <div class="space-y-6">
                <h1 class="text-4xl font-bold text-white leading-tight">
                    Crear una cuenta
                </h1>
                <p class="text-xl text-white/80">
                    Únete al sistema de gestión de calidad más avanzado.
                </p>
            </div>
            
            <p class="text-sm text-white/60">
                &copy; {{ date('Y') }} QA365 - Powered By <a href="https://wa.me/+51901235322" target="_blank" class="font-bold hover:text-indigo-500 transition-colors">Bearlytic's</a>
            </p>
        </div>

        <!-- Right Panel - Form -->
        <div class="flex-1 flex items-center justify-center p-8 bg-gray-50 dark:bg-gray-950">
            <div class="w-full max-w-md space-y-8">
                <!-- Mobile Logo -->
                <div class="lg:hidden text-center">
                    <a href="/" class="inline-flex items-center gap-2">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 flex items-center justify-center">
                            <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                        <span class="text-2xl font-bold text-gray-900 dark:text-white">QualityAI</span>
                    </a>
                </div>

                <div class="text-center lg:text-left">
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Crear cuenta</h2>
                    <p class="mt-2 text-gray-600 dark:text-gray-400">Completa el formulario para registrarte</p>
                </div>

                <form method="POST" action="{{ route('register') }}" class="space-y-6">
                    @csrf

                    <div class="form-group">
                        <label for="name" class="form-label">Nombre Completo</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" 
                            class="form-input" placeholder="Tu nombre" required autofocus>
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="form-group">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" 
                            class="form-input" placeholder="tu@email.com" required>
                        <x-input-error :messages="$errors->get('email')" class="mt-1" />
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" name="password" id="password" 
                            class="form-input" placeholder="••••••••" required>
                        <x-input-error :messages="$errors->get('password')" class="mt-1" />
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation" class="form-label">Confirmar Contraseña</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" 
                            class="form-input" placeholder="••••••••" required>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1" />
                    </div>

                    <button type="submit" class="btn-primary w-full btn-lg">
                        Registrarse
                    </button>

                    <p class="text-center text-sm text-gray-600 dark:text-gray-400">
                        ¿Ya tienes cuenta?
                        <a href="{{ route('login') }}" class="font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                            Iniciar Sesión
                        </a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</x-guest-layout>
