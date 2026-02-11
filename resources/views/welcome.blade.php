<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'QA Center') }} - Evaluación de Calidad con IA</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Orbitron:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>
<body class="bg-white dark:bg-[#0a0a0a] text-gray-900 dark:text-gray-100">
    
    <!-- Navigation -->
    <nav class="fixed top-0 inset-x-0 z-50 bg-white/80 dark:bg-[#0a0a0a]/80 backdrop-blur-xl border-b border-gray-200/50 dark:border-neutral-800/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="/" class="flex items-center gap-3">
                    <img src="{{ asset('infoarchives/QALogo.png') }}" alt="QA Center" 
                        class="h-9 w-auto object-contain transition-all duration-300 filter grayscale hover:grayscale-0 hover:filter-none dark:invert dark:hover:invert-0">
                </a>

                <!-- Nav Links -->
                <div class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Características</a>
                    <a href="#how-it-works" class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Cómo Funciona</a>
                    
                    <!-- Theme Toggle -->
                    <div x-data="themeToggle">
                        <button @click="toggle()" class="btn-icon">
                            <svg x-show="!isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <svg x-show="isDark" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </button>
                    </div>
                    
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary">Ir al Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn-primary">Iniciar Sesión</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center overflow-hidden pt-16">
        <!-- Background Effects -->
        <!-- Background Effects -->
        <div class="absolute inset-0 overflow-hidden">
            <video autoplay loop muted playsinline class="absolute inset-0 w-full h-full object-cover">
                <source src="{{ asset('infoarchives/hoverloop.mp4') }}" type="video/mp4">
            </video>
            <!-- Overlay para mejorar legibilidad -->
            <div class="absolute inset-0 bg-white/75 dark:bg-gray-950/75 backdrop-blur-[2px]"></div>
            
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-gradient-to-r from-indigo-500/10 to-purple-500/10 dark:from-white/5 dark:to-white/5 rounded-full blur-3xl"></div>
        </div>
        
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <!-- Badge -->
            <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-50 dark:bg-white/5 border border-indigo-200 dark:border-white/10 mb-8">
                <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                <span class="text-sm font-medium text-indigo-700 dark:text-indigo-400">Potenciado por Inteligencia Artificial</span>
            </div>
            
            <!-- Title -->
            <h1 class="text-5xl md:text-7xl font-bold tracking-tight mb-6 flex flex-col items-center md:grid md:grid-cols-2 md:gap-x-2">
                <span class="md:text-right">Transforma</span>
                <span x-data="typewriter" class="inline-block md:text-left relative">
                    <span x-html="formattedText" class="whitespace-nowrap"></span><span class="animate-pulse text-gray-900 dark:text-white">|</span>
                </span>
            </h1><!-- End Title -->
            
            <!-- Subtitle -->
            <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed">
                Evalúa el 100% de tus llamadas automáticamente. Detecta oportunidades de mejora y eleva la calidad de servicio con nuestra plataforma de QA inteligente.
            </p>
            
            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary text-lg px-8 py-4">
                        Ir al Dashboard
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary text-lg px-8 py-4">
                        Comenzar Ahora
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                @endauth
                <a href="#features" class="btn-secondary text-lg px-8 py-4">
                    Ver Características
                </a>
            </div>
            
            <!-- Stats -->
            <div class="mt-20 grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="text-4xl font-bold text-gray-900 dark:text-white">100%</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Cobertura de Llamadas</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-gray-900 dark:text-white">95%</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Precisión IA</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-gray-900 dark:text-white">3x</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Más Rápido</div>
                </div>
                <div class="text-center">
                    <div class="text-4xl font-bold text-gray-900 dark:text-white">24/7</div>
                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">Disponibilidad</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 bg-gray-50 dark:bg-[#0a0a0a]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Todo lo que Necesitas</h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    Herramientas diseñadas para equipos de calidad de alto rendimiento.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="card card-hover p-8">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Evaluación con IA</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                        Nuestra IA analiza cada transcripción y extrae evidencias precisas para cada criterio de evaluación.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="card card-hover p-8">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Dashboards en Tiempo Real</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                        Visualiza métricas clave, tendencias y rendimiento de agentes al instante con gráficos interactivos.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="card card-hover p-8">
                    <div class="w-14 h-14 rounded-2xl bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Fichas Personalizables</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                        Crea formularios de calidad adaptados a cada campaña con atributos ponderados y criterios críticos.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it Works -->
    <section id="how-it-works" class="py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Cómo Funciona</h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    En solo 3 pasos, transforma tu proceso de evaluación de calidad.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto mb-6 text-2xl font-bold">1</div>
                    <h3 class="text-lg font-bold mb-2">Carga Transcripciones</h3>
                    <p class="text-gray-600 dark:text-gray-400">Sube archivos .txt de tus llamadas de forma individual o masiva.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center mx-auto mb-6 text-2xl font-bold">2</div>
                    <h3 class="text-lg font-bold mb-2">La IA Evalúa</h3>
                    <p class="text-gray-600 dark:text-gray-400">Nuestra IA analiza y extrae evidencias automáticamente.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-6 text-2xl font-bold">3</div>
                    <h3 class="text-lg font-bold mb-2">Toma Decisiones</h3>
                    <p class="text-gray-600 dark:text-gray-400">Visualiza resultados y desarrolla planes de mejora.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-50 dark:bg-[#0a0a0a] border-t border-gray-200 dark:border-neutral-800 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <img src="{{ asset('infoarchives/QALogo.png') }}" alt="QA Center" 
                        class="h-8 w-auto object-contain transition-all duration-300 filter grayscale hover:grayscale-0 hover:filter-none dark:invert dark:hover:invert-0">
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    &copy; {{ date('Y') }} QA365 - Powered By <a href="https://wa.me/+51901235322" target="_blank" class="font-bold hover:text-indigo-500 transition-colors">Bearlytic's</a>
                </p>
            </div>
        </div>
    </footer>

    <style>[x-cloak] { display: none !important; }</style>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('typewriter', () => ({
                text: '',
                words: [
                    { text: 'tu BPO', split: 3 },
                    { text: 'a A365', split: 2 }
                ],
                wordIndex: 0,
                charIndex: 0,
                isDeleting: false,
                typeSpeed: 100,
                deleteSpeed: 50,
                wait: 2000,
                
                init() {
                    this.type();
                },
                
                get formattedText() {
                    const currentWord = this.words[this.wordIndex % this.words.length];
                    const splitAt = currentWord.split;
                    
                    if (this.text.length <= splitAt) {
                        return `<span class="text-gray-900 dark:text-white">${this.text}</span>`;
                    }
                    
                    const prefix = this.text.substring(0, splitAt);
                    const suffix = this.text.substring(splitAt);
                    
                    return `<span class="text-gray-900 dark:text-white">${prefix}</span><span class="text-gradient font-['Orbitron'] tracking-wider">${suffix}</span>`;
                },
                
                type() {
                    const currentIndex = this.wordIndex % this.words.length;
                    const fullTxt = this.words[currentIndex].text;
                    
                    if (this.isDeleting) {
                        this.text = fullTxt.substring(0, this.charIndex - 1);
                        this.charIndex--;
                    } else {
                        this.text = fullTxt.substring(0, this.charIndex + 1);
                        this.charIndex++;
                    }

                    let typeSpeed = this.typeSpeed;
                    if (this.isDeleting) typeSpeed /= 2;

                    if (!this.isDeleting && this.text === fullTxt) {
                        typeSpeed = this.wait;
                        this.isDeleting = true;
                    } else if (this.isDeleting && this.text === '') {
                        this.isDeleting = false;
                        this.wordIndex++;
                        typeSpeed = 500;
                    }

                    setTimeout(() => this.type(), typeSpeed);
                }
            }))
        })
    </script>
</body>
</html>
