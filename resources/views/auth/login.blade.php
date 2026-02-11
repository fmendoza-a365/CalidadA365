<x-guest-layout>
    <div class="min-h-screen flex">
        <!-- Left Panel - Branding -->
        <div class="hidden lg:flex lg:w-1/2 relative bg-gray-900 overflow-hidden">
            <!-- Background Gradient -->
            <div class="absolute inset-0 bg-gradient-to-br from-indigo-600 via-purple-700 to-pink-600 dark:from-neutral-900 dark:via-neutral-900 dark:to-neutral-950"></div>
            
            <!-- Pattern Overlay -->
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5"/>
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" />
                </svg>
            </div>
            
            <!-- Content -->
            <div class="relative z-10 flex flex-col justify-center items-start px-12 lg:px-16 text-white text-left h-full">
                <img src="{{ asset('infoarchives/QALogo.png') }}" alt="QA Center" 
                    class="h-16 w-auto object-contain mb-8 transition-all duration-300 filter brightness-0 invert hover:filter-none hover:brightness-100 hover:invert-0">
                
                <h1 class="text-4xl lg:text-5xl font-bold mb-6 leading-tight">
                    Centro de<br>Excelencia<br>en Calidad
                </h1>
                
                <p class="text-lg text-white/80 max-w-md leading-relaxed">
                    Plataforma inteligente de aseguramiento de calidad potenciada por IA para transformar cada interacción en una oportunidad de mejora.
                </p>
                
                <div class="mt-12 flex items-center gap-4">
                    <div class="flex -space-x-3">
                        <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-sm font-bold border-2 border-white/30">QA</div>
                        <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-sm font-bold border-2 border-white/30">AI</div>
                        <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center text-sm font-bold border-2 border-white/30">+</div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Evaluación Automatizada</p>
                        <p class="text-sm text-white/60">Resultados en minutos</p>
                    </div>
                </div>
            </div>
            
            <!-- Decorative Elements -->
            <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-pink-500/30 rounded-full blur-3xl"></div>
            <div class="absolute top-32 -left-16 w-64 h-64 bg-indigo-400/30 rounded-full blur-3xl"></div>
        </div>

        <!-- Right Panel - Form -->
        <div class="flex-1 flex items-center justify-center p-8">
            <div class="w-full max-w-md">
                <!-- Mobile Logo -->
                <div class="lg:hidden flex items-center justify-center mb-8">
                    <img src="{{ asset('infoarchives/QALogo.png') }}" alt="QA Center" 
                        class="h-10 w-auto transition-all duration-300 filter grayscale hover:grayscale-0 hover:filter-none dark:invert dark:hover:invert-0">
                </div>

                <!-- Header -->
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Bienvenido de vuelta</h2>
                    <p class="text-gray-600 dark:text-gray-400">Ingresa tus credenciales para continuar</p>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <!-- Form -->
                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <!-- Email -->
                    <div>
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </div>
                            <input 
                                id="email" 
                                name="email" 
                                type="email" 
                                value="{{ old('email') }}"
                                class="form-input pl-12" 
                                placeholder="nombre@empresa.com"
                                required 
                                autofocus 
                                autocomplete="username">
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div>
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input 
                                id="password" 
                                name="password" 
                                type="password" 
                                class="form-input pl-12" 
                                placeholder="••••••••"
                                required 
                                autocomplete="current-password">
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Remember & Forgot -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="remember" class="form-checkbox">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Recordarme</span>
                        </label>
                        
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                ¿Olvidaste tu contraseña?
                            </a>
                        @endif
                    </div>

                    <!-- Submit -->
                    <button type="submit" class="btn-primary w-full py-3 text-base">
                        Iniciar Sesión
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </button>
                </form>

                <!-- Footer -->
                <p class="mt-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    &copy; {{ date('Y') }} QA365 - Powered By <a href="https://wa.me/+51901235322" target="_blank" class="font-bold hover:text-indigo-500 transition-colors">Bearlytic's</a>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
