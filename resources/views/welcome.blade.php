<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'QA Center') }} - Evaluación de Calidad con IA</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Orbitron:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>

<body class="bg-white dark:bg-[#0a0a0a] text-gray-900 dark:text-gray-100">

    <!-- Navigation -->
    <nav
        class="fixed top-0 inset-x-0 z-50 bg-white/80 dark:bg-[#0a0a0a]/80 backdrop-blur-xl border-b border-gray-200/50 dark:border-neutral-800/50">
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="/" class="flex items-center gap-3">
                    <img src="{{ asset('infoarchives/QALogo.png') }}" alt="QA Center"
                        class="h-9 w-auto object-contain transition-all duration-300 filter grayscale hover:grayscale-0 hover:filter-none dark:invert dark:hover:invert-0">
                </a>

                <!-- Nav Links -->
                <div class="hidden md:flex items-center gap-8">
                    <a href="#features"
                        class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Características</a>
                    <a href="#how-it-works"
                        class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">Cómo
                        Funciona</a>
                    <a href="#mobile-app"
                        class="text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">App
                        Android</a>
                    <a href="/docs-executive.html" target="_blank"
                        class="text-sm font-medium text-brand-600 dark:text-brand-400 hover:text-brand-700 dark:hover:text-brand-300 transition-colors flex items-center gap-1">
                        Manual Ejecutivo
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>

                    <!-- Theme Toggle -->
                    <div x-data="themeToggle">
                        <button @click="toggle()" class="btn-icon">
                            <svg x-show="!isDark" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <svg x-show="isDark" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
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

            <div
                class="absolute top-1/4 left-1/2 -translate-x-1/2 w-[800px] h-[800px] bg-gradient-to-r from-indigo-500/10 to-purple-500/10 dark:from-white/5 dark:to-white/5 rounded-full blur-3xl">
            </div>
        </div>

        <div class="relative z-10 w-full mx-auto px-4 sm:px-6 lg:px-8 text-center flex flex-col items-center">
            <!-- Interactive Animated Logo -->
            <div class="logo-shell-hero mb-6 max-w-sm sm:max-w-md md:max-w-lg mx-auto w-full">
                <div class="qa-logo-hero-wrap qa-logo-link is-playing">
                    <svg class="w-full h-auto qa-logo-svg" viewBox="0 0 15620 6990" role="img" aria-labelledby="hero-title hero-desc">
                        <title id="hero-title">QA365 Logo Animado</title>
                        <desc id="hero-desc">Logo interactivo y animado de QA365 en el centro del Hero</desc>
                        <g transform="translate(0,6990) scale(1,-1)">
                            <path class="logo-part blue slash-main" d="M1121 6977 c-46 -15 -98 -69 -111 -115 -14 -53 -15 -51 293 -992 417 -1278 1121 -3450 1663 -5125 108 -335 206 -629 216 -652 50 -106 191 -119 271 -24 22 27 27 42 27 86 0 72 -29 169 -298 995 -383 1173 -1246 3833 -1546 4765 -334 1036 -326 1014 -390 1044 -56 27 -85 31 -125 18z"/>
                            <path class="logo-part red num-6" d="M6905 5083 c-115 -42 -229 -133 -402 -324 -544 -604 -858 -1138 -974 -1659 -15 -69 -22 -144 -26 -275 -4 -155 -2 -198 17 -309 47 -277 135 -464 294 -621 160 -159 363 -253 644 -300 141 -24 419 -17 539 14 191 48 355 129 506 251 140 112 250 274 313 458 49 143 58 212 57 442 -1 245 -14 305 -97 475 -124 252 -324 408 -637 499 -104 30 -334 66 -418 66 -34 0 -61 4 -61 8 0 13 77 120 134 187 28 33 138 150 245 259 295 306 326 352 326 496 0 73 -4 93 -28 142 -35 72 -105 141 -176 175 -74 35 -184 42 -256 16z m-57 -1918 c60 -18 161 -105 211 -183 131 -204 40 -498 -186 -604 -59 -28 -81 -33 -169 -36 -95 -4 -104 -2 -180 29 -125 52 -215 154 -248 284 -59 224 71 459 286 520 64 18 208 13 286 -10z"/>
                            <path class="logo-part red num-3" d="M3495 5076 c-341 -78 -637 -314 -748 -595 -26 -65 -30 -90 -31 -171 -1 -110 19 -168 82 -233 70 -73 162 -104 256 -86 97 18 163 60 309 198 161 152 220 187 333 197 99 9 171 -13 235 -73 65 -60 84 -111 76 -206 -15 -186 -80 -252 -334 -341 -246 -85 -317 -154 -318 -306 0 -60 5 -86 26 -130 52 -110 106 -151 285 -213 143 -50 238 -101 302 -160 74 -71 104 -132 110 -228 8 -139 -38 -236 -152 -314 l-58 -40 -140 -3 c-161 -4 -191 3 -298 74 -62 40 -182 156 -228 219 l-21 30 19 -70 c11 -38 91 -290 179 -560 l158 -490 149 3 c278 6 412 32 591 117 317 149 512 394 585 730 19 92 16 333 -6 430 -57 251 -196 453 -404 585 -39 25 -71 49 -71 53 -1 5 32 54 73 110 198 273 277 506 247 724 -48 344 -306 622 -681 735 -86 26 -432 35 -525 14z"/>
                            <path class="logo-part red num-5" d="M9224 4981 l-220 -6 -59 -30 c-86 -44 -111 -85 -130 -210 -13 -89 -88 -813 -115 -1100 -14 -154 -14 -283 0 -335 15 -57 80 -124 135 -139 88 -24 165 -11 385 68 136 48 209 58 309 41 152 -26 285 -125 331 -247 18 -47 21 -79 21 -178 0 -206 -43 -346 -140 -448 -28 -30 -77 -67 -108 -83 -55 -27 -65 -29 -173 -28 -176 1 -284 43 -468 181 -110 83 -159 105 -231 105 -74 0 -136 -29 -212 -99 -83 -78 -104 -126 -103 -238 1 -68 6 -103 23 -143 75 -184 281 -359 521 -442 185 -63 509 -88 730 -56 259 38 469 143 650 325 180 180 288 399 331 667 18 116 16 389 -5 504 -75 423 -300 693 -673 807 -84 25 -99 27 -308 27 -190 1 -227 -2 -273 -18 -29 -10 -55 -16 -57 -15 -7 8 15 286 26 324 17 61 82 120 146 133 29 7 218 16 420 22 524 14 543 15 597 51 56 38 103 107 112 167 4 25 4 86 1 135 -8 119 -39 185 -105 225 -47 27 -49 27 -287 34 -278 7 -753 7 -1071 -1z"/>
                            <path class="logo-part blue headset" d="M13205 4929 c-139 -9 -242 -31 -410 -89 -398 -138 -728 -394 -965 -750 -68 -103 -113 -160 -139 -177 -14 -9 -63 -24 -110 -34 -122 -27 -197 -67 -267 -142 -73 -77 -119 -162 -134 -247 -8 -41 -13 -192 -14 -410 -1 -394 6 -447 72 -559 76 -129 196 -209 352 -234 47 -7 95 -19 108 -26 13 -7 43 -44 68 -82 49 -77 76 -94 139 -85 54 7 115 70 115 118 0 18 -23 85 -51 148 -67 150 -123 314 -146 420 -28 131 -25 478 5 616 32 147 65 242 137 389 82 169 164 284 300 420 139 138 262 226 440 314 241 120 342 141 676 141 333 0 453 -23 675 -130 275 -133 537 -369 694 -625 85 -138 159 -318 195 -476 25 -112 37 -329 25 -484 -17 -226 -65 -386 -186 -625 -112 -222 -188 -310 -339 -395 -101 -57 -151 -65 -410 -65 -252 1 -298 7 -365 50 -31 20 -69 33 -120 41 -90 14 -361 7 -405 -10 -100 -40 -164 -193 -118 -281 35 -67 15 -65 658 -64 348 1 613 6 662 13 146 19 257 78 396 211 103 97 162 172 241 307 36 61 77 120 90 131 15 12 55 23 108 30 214 30 381 187 418 392 15 80 15 670 0 783 -19 154 -126 305 -264 372 -28 14 -85 32 -126 40 -135 28 -136 29 -262 212 -211 309 -438 508 -770 674 -204 103 -409 155 -638 165 -69 2 -145 6 -170 7 -25 2 -99 0 -165 -4z"/>
                            <path class="logo-part blue slash-left" d="M1116 4764 c-4 -11 -47 -127 -96 -259 -48 -132 -222 -600 -385 -1040 -668 -1799 -649 -1743 -623 -1820 36 -108 197 -115 250 -12 33 65 151 352 216 524 33 87 227 618 431 1181 l372 1023 -67 187 c-37 103 -72 198 -79 211 -11 22 -12 22 -19 5z"/>
                            <path class="logo-part red headset-face" d="M12629 4046 c-114 -41 -259 -218 -374 -458 -98 -205 -133 -384 -122 -623 14 -275 82 -470 187 -527 31 -17 49 -19 150 -14 63 2 187 18 275 35 219 41 345 59 512 73 121 10 166 9 318 -5 98 -10 227 -27 289 -38 343 -60 405 -69 484 -69 104 0 137 16 182 87 75 118 113 292 114 523 1 243 -25 361 -129 578 -96 199 -225 361 -337 419 -78 40 -175 36 -438 -17 -330 -68 -396 -66 -777 15 -173 37 -272 43 -334 21z m177 -595 c173 -79 210 -277 74 -394 -172 -147 -439 25 -375 241 40 135 182 207 301 153z m1340 0 c75 -34 134 -133 134 -225 0 -53 -32 -117 -82 -161 -80 -73 -154 -83 -253 -36 -71 33 -110 75 -126 134 -49 182 158 365 327 288z"/>
                            <path class="logo-part blue slash-small" d="M910 2603 c-83 -8 -127 -85 -95 -168 28 -75 9 -72 416 -79 201 -3 456 -3 567 0 l200 7 -42 121 -43 121 -479 1 c-263 0 -499 -1 -524 -3z"/>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- Badge -->
            <div
                class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-50 dark:bg-white/5 border border-indigo-200 dark:border-white/10 mb-8">
                <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span>
                <span class="text-sm font-medium text-indigo-700 dark:text-indigo-400">Potenciado por Inteligencia
                    Artificial</span>
            </div>

            <!-- Title -->
            <h1
                class="text-5xl md:text-7xl font-bold tracking-tight mb-6 flex flex-col items-center md:grid md:grid-cols-2 md:gap-x-2">
                <span class="md:text-right">Transforma</span>
                <span x-data="typewriter" class="inline-block md:text-left relative">
                    <span x-html="formattedText" class="whitespace-nowrap"></span><span
                        class="animate-pulse text-gray-900 dark:text-white">|</span>
                </span>
            </h1><!-- End Title -->

            <!-- Subtitle -->
            <p class="text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto mb-10 leading-relaxed">
                Evalúa el 100% de tus llamadas automáticamente. Detecta oportunidades de mejora y eleva la calidad de
                servicio con nuestra plataforma de QA inteligente.
            </p>

            <!-- CTA Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary text-lg px-8 py-4">
                        Ir al Dashboard
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary text-lg px-8 py-4">
                        Comenzar Ahora
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 7l5 5m0 0l-5 5m5-5H6" />
                        </svg>
                    </a>
                @endauth
                <a href="#features" class="btn-secondary text-lg px-8 py-4">
                    Ver Características
                </a>
                <a href="#mobile-app" class="btn-secondary text-lg px-8 py-4">
                    App Android
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
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Todo lo que Necesitas</h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    Herramientas diseñadas para equipos de calidad de alto rendimiento.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="card card-hover p-8">
                    <div
                        class="w-14 h-14 rounded-2xl bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Evaluación con IA</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                        Nuestra IA analiza cada transcripción y extrae evidencias precisas para cada criterio de
                        evaluación.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="card card-hover p-8">
                    <div
                        class="w-14 h-14 rounded-2xl bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Dashboards en Tiempo Real</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                        Visualiza métricas clave, tendencias y rendimiento de agentes al instante con gráficos
                        interactivos.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="card card-hover p-8">
                    <div
                        class="w-14 h-14 rounded-2xl bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center mb-6">
                        <svg class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Fichas Personalizables</h3>
                    <p class="text-gray-600 dark:text-gray-400 leading-relaxed">
                        Crea formularios de calidad adaptados a cada campaña con atributos ponderados y criterios
                        críticos.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Mobile App -->
    <section id="mobile-app" class="py-24 bg-white dark:bg-[#0a0a0a] border-y border-gray-200 dark:border-neutral-800">
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-[1fr_420px] gap-12 items-center max-w-6xl mx-auto">
                <div>
                    <div
                        class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 mb-6">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span class="text-sm font-medium text-emerald-700 dark:text-emerald-300">Disponible para
                            Android</span>
                    </div>
                    <h2 class="text-3xl md:text-4xl font-bold mb-4">QA365 Mobile</h2>
                    <p class="text-lg text-gray-600 dark:text-gray-400 leading-relaxed mb-8">
                        Revisa resultados, alertas críticas, evaluaciones recientes y señales de audio desde el móvil,
                        conectado al mismo sistema de calidad.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ asset('downloads/qa365-mobile.apk') }}" download
                            class="btn-primary text-lg px-8 py-4">
                            Descargar APK
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-secondary text-lg px-8 py-4">Ir al
                                Dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn-secondary text-lg px-8 py-4">Iniciar Sesión</a>
                        @endauth
                    </div>
                    <div class="mt-6 text-sm text-gray-500 dark:text-gray-400">
                        Archivo: <span class="font-semibold text-gray-700 dark:text-gray-300">qa365-mobile.apk</span>
                    </div>
                </div>

                <div class="rounded-[2rem] border border-gray-200 dark:border-neutral-800 bg-gray-100 dark:bg-neutral-950 p-4 shadow-2xl">
                    <div class="rounded-[1.5rem] bg-white dark:bg-[#111111] border border-gray-200 dark:border-neutral-800 overflow-hidden">
                        <div class="px-5 py-4 border-b border-gray-200 dark:border-neutral-800 flex items-center justify-between">
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">QA365 Mobile</div>
                                <div class="font-bold">Alertas de calidad</div>
                            </div>
                            <span class="px-2 py-1 rounded-full bg-emerald-100 dark:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 text-xs font-bold">Live</span>
                        </div>
                        <div class="p-5 space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-lg border border-gray-200 dark:border-neutral-800 p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Promedio</div>
                                    <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-300">86%</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 dark:border-neutral-800 p-3">
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Alertas</div>
                                    <div class="text-2xl font-bold text-amber-600 dark:text-amber-300">4</div>
                                </div>
                            </div>
                            <div class="rounded-lg border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-500/10 p-3">
                                <div class="text-sm font-bold text-rose-700 dark:text-rose-300">Revisar evaluación crítica</div>
                                <div class="text-xs text-rose-700/80 dark:text-rose-200/80 mt-1">Riesgo alto de experiencia y cliente sin resolver.</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 dark:border-neutral-800 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold">Claro</span>
                                    <span class="text-sm font-bold text-emerald-600 dark:text-emerald-300">91%</span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tiempo muerto: 00:00</div>
                            </div>
                            <div class="rounded-lg border border-gray-200 dark:border-neutral-800 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm font-semibold">BCP</span>
                                    <span class="text-sm font-bold text-rose-600 dark:text-rose-300">58%</span>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Tiempo muerto: 00:45</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How it Works -->
    <section id="how-it-works" class="py-24">
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">Cómo Funciona</h2>
                <p class="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
                    En solo 3 pasos, transforma tu proceso de evaluación de calidad.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div
                        class="w-16 h-16 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 flex items-center justify-center mx-auto mb-6 text-2xl font-bold">
                        1</div>
                    <h3 class="text-lg font-bold mb-2">Carga Transcripciones</h3>
                    <p class="text-gray-600 dark:text-gray-400">Sube archivos .txt de tus llamadas de forma individual o
                        masiva.</p>
                </div>
                <div class="text-center">
                    <div
                        class="w-16 h-16 rounded-full bg-purple-100 dark:bg-purple-500/20 text-purple-600 dark:text-purple-400 flex items-center justify-center mx-auto mb-6 text-2xl font-bold">
                        2</div>
                    <h3 class="text-lg font-bold mb-2">La IA Evalúa</h3>
                    <p class="text-gray-600 dark:text-gray-400">Nuestra IA analiza y extrae evidencias automáticamente.
                    </p>
                </div>
                <div class="text-center">
                    <div
                        class="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center mx-auto mb-6 text-2xl font-bold">
                        3</div>
                    <h3 class="text-lg font-bold mb-2">Toma Decisiones</h3>
                    <p class="text-gray-600 dark:text-gray-400">Visualiza resultados y desarrolla planes de mejora.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-50 dark:bg-[#0a0a0a] border-t border-gray-200 dark:border-neutral-800 py-12">
        <div class="w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <img src="{{ asset('infoarchives/QALogo.png') }}" alt="QA Center"
                        class="h-8 w-auto object-contain transition-all duration-300 filter grayscale hover:grayscale-0 hover:filter-none dark:invert dark:hover:invert-0">
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    &copy; {{ date('Y') }} QA365 - Powered By <a href="https://wa.me/+51901235322" target="_blank"
                        class="font-bold hover:text-indigo-500 transition-colors">Bearlytic's</a>
                </p>
            </div>
        </div>
    </footer>

    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Contenedor del Logo interactivo en el Hero */
        .logo-shell-hero {
            position: relative;
            margin-inline: auto;
            width: min(420px, 80vw);
            padding: 10px;
            z-index: 10;
        }

        .qa-logo-hero-wrap {
            position: relative;
            overflow: visible;
            cursor: pointer;
            display: flex;
            justify-content: center;
        }

        /* Variables y tokens de animación para el logo */
        :root {
            --qa-blue: #2D5792;
            --qa-red: #C4090F;
            --ease-out: cubic-bezier(.16, 1, .3, 1);
            --ease-soft: cubic-bezier(.22, .61, .36, 1);
        }

        /* Estilos e interacción del Logo SVG */
        .qa-logo-svg {
            display: block;
            overflow: visible;
            transition: transform 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
            transform-origin: center;
        }

        /* En reposo, todas las partes del logo tienen un color neutro grisáceo y están ocultas para la animación de entrada */
        .qa-logo-svg .logo-part {
            opacity: 0;
            fill: #94a3b8; /* Slate 400 */
            transition: fill 0.4s ease, transform 0.4s ease, filter 0.4s ease, opacity 0.4s ease;
            transform-origin: center;
            transform-box: fill-box;
        }

        .dark .qa-logo-svg .logo-part {
            fill: #4b5563; /* Slate 600 */
        }

        /* Animaciones elegantes de entrada (is-playing) */
        .is-playing .slash-left,
        .is-playing .slash-main,
        .is-playing .slash-small {
            animation: elegantLineIn 1.15s var(--ease-out) forwards;
        }

        .is-playing .slash-left { animation-delay: .12s; }
        .is-playing .slash-main { animation-delay: .24s; }
        .is-playing .slash-small { animation-delay: .42s; }

        .is-playing .num-3,
        .is-playing .num-6,
        .is-playing .num-5 {
            animation: elegantNumberIn .98s var(--ease-out) forwards;
        }

        .is-playing .num-3 { animation-delay: .62s; }
        .is-playing .num-6 { animation-delay: .78s; }
        .is-playing .num-5 { animation-delay: .94s; }

        .is-playing .headset,
        .is-playing .headset-face {
            animation: elegantHeadsetIn 1.05s var(--ease-out) forwards;
        }

        .is-playing .headset { animation-delay: 1.14s; }
        .is-playing .headset-face { animation-delay: 1.28s; }

        .is-playing .qa-logo-svg {
            animation: logoSettle 1.8s var(--ease-out) 1.55s both;
        }

        /* Animación de respiración al pasar el mouse por encima del enlace o contenedor del Hero */
        @keyframes qaLogoBreathe {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .qa-logo-link:hover .qa-logo-svg {
            animation: qaLogoBreathe 2s ease-in-out infinite;
        }

        /* Cuando el mouse pasa sobre una parte específica, esta revela su color real y se desplaza ligeramente */
        .qa-logo-svg .logo-part.blue:hover {
            fill: var(--qa-blue) !important; /* Azul original */
            filter: drop-shadow(0 15px 30px rgba(45, 87, 146, 0.45));
            transform: scale(1.08);
            z-index: 10;
        }

        .qa-logo-svg .logo-part.red:hover {
            fill: var(--qa-red) !important; /* Rojo original */
            filter: drop-shadow(0 15px 30px rgba(196, 9, 15, 0.45));
            transform: scale(1.08);
            z-index: 10;
        }

        @keyframes elegantLineIn {
            0% { opacity: 0; transform: translateY(-28px) scaleY(.88); filter: blur(8px); }
            60% { opacity: 1; filter: blur(0); }
            100% { opacity: 1; transform: translateY(0) scaleY(1); filter: blur(0); }
        }

        @keyframes elegantNumberIn {
            0% { opacity: 0; transform: translateY(24px) scale(.94); filter: blur(10px); }
            68% { opacity: 1; transform: translateY(-2px) scale(1.01); filter: blur(0); }
            100% { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); }
        }

        @keyframes elegantHeadsetIn {
            0% { opacity: 0; transform: translateX(38px) scale(.96); filter: blur(9px); }
            70% { opacity: 1; transform: translateX(-2px) scale(1.005); filter: blur(0); }
            100% { opacity: 1; transform: translateX(0) scale(1); filter: blur(0); }
        }

        @keyframes logoSettle {
            0% { filter: drop-shadow(0 14px 24px rgba(13, 27, 42, .10)); }
            35% { filter: drop-shadow(0 18px 34px rgba(45, 87, 146, .20)); }
            100% { filter: drop-shadow(0 14px 24px rgba(13, 27, 42, .11)); }
        }
    </style>
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
