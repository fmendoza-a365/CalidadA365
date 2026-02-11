<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'QA Center') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <script>
        // Prevent flash of wrong theme
        if (localStorage.theme === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    </script>
</head>

<body class="h-full">
    <x-notifications-container />
    <div x-data="sidebar" class="app-container">

        <!-- Mobile Overlay -->
        <div x-show="open" x-transition:enter="transition-opacity ease-out duration-300"
            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0" @click="open = false" class="sidebar-overlay lg:hidden"></div>

        <!-- Sidebar -->
        <aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="sidebar">
            <!-- Logo -->
            <div class="flex items-center justify-start h-16 px-6 border-b border-gray-200 dark:border-gray-800">
                <img src="{{ asset('infoarchives/QALogo.png') }}" alt="QA Center"
                    class="h-9 w-auto object-contain transition-all duration-300 filter grayscale hover:grayscale-0 hover:filter-none dark:invert dark:hover:invert-0">
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto p-4 space-y-1 scrollbar-thin">
                <p class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Principal</p>

                <a href="{{ route('dashboard') }}"
                    class="nav-item {{ request()->routeIs('dashboard') ? 'nav-item-active' : '' }}">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="{{ route('dashboard.quality') }}"
                    class="nav-item {{ request()->routeIs('dashboard.quality') ? 'nav-item-active' : '' }}">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    <span>Dashboard Calidad</span>
                </a>

                @if(auth()->user()->hasAnyRole(['admin', 'qa_manager', 'supervisor']))
                    <p class="px-3 py-2 mt-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Gestión</p>

                    <a href="{{ route('campaigns.index') }}"
                        class="nav-item {{ request()->routeIs('campaigns.*') ? 'nav-item-active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        <span>Campañas</span>
                    </a>

                    <a href="{{ route('quality-forms.index') }}"
                        class="nav-item {{ request()->routeIs('quality-forms.*') ? 'nav-item-active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>Fichas de Calidad</span>
                    </a>

                    <a href="{{ route('transcripts.index') }}"
                        class="nav-item {{ request()->routeIs('transcripts.*') ? 'nav-item-active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Transcripciones</span>
                    </a>

                    <a href="{{ route('insights.index') }}"
                        class="nav-item {{ request()->routeIs('insights.*') ? 'nav-item-active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                        <span>Insights IA</span>
                    </a>
                @endif

                <p class="px-3 py-2 mt-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Operación</p>

                <a href="{{ route('evaluations.index') }}"
                    class="nav-item {{ request()->routeIs('evaluations.*') ? 'nav-item-active' : '' }}">
                    <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                    </svg>
                    <span>Evaluaciones</span>
                </a>
                @if(auth()->user()->hasRole('admin') || auth()->user()->can('view_users') || auth()->user()->can('manage_roles'))
                    <p class="px-3 py-2 mt-6 text-xs font-semibold text-gray-400 uppercase tracking-wider">Administración
                    </p>

                    @if(auth()->user()->hasRole('admin') || auth()->user()->can('view_users'))
                        <a href="{{ route('users.index') }}"
                            class="nav-item {{ request()->routeIs('users.*') ? 'nav-item-active' : '' }}">
                            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <span>Usuarios</span>
                        </a>
                    @endif

                    @if(auth()->user()->hasRole('admin') || auth()->user()->can('manage_roles'))
                        <a href="{{ route('roles.index') }}"
                            class="nav-item {{ request()->routeIs('roles.*') ? 'nav-item-active' : '' }}">
                            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>Roles y Permisos</span>
                        </a>
                    @endif
                @endif

                @if(auth()->user()->hasRole('admin') || auth()->user()->can('manage_ai_settings'))
                    <p class="px-3 py-2 mt-2 text-xs font-semibold text-gray-400 uppercase tracking-wider">Configuración</p>

                    <a href="{{ route('settings.ai') }}"
                        class="nav-item {{ request()->routeIs('settings.*') ? 'nav-item-active' : '' }}">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.384-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <span>IA y Modelos</span>
                    </a>
                @endif
            </nav>

            <!-- User Section -->
            <div class="p-4 border-t border-gray-200 dark:border-gray-800">
                <a href="{{ route('profile.edit') }}"
                    class="flex items-center gap-3 mb-3 p-2 -mx-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors group">
                    <div
                        class="avatar avatar-md bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900 transition-colors">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-white truncate">{{ auth()->user()->name }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                            {{ auth()->user()->roles->first()?->name ?? 'Usuario' }}</p>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full nav-item text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10">
                        <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>Cerrar Sesión</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <header class="app-header">
                <div class="flex items-center gap-4">
                    <!-- Mobile Menu Toggle -->
                    <button @click="toggle()" class="btn-icon lg:hidden">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Page Title -->
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                        @if(isset($header))
                            {{ $header }}
                        @else
                            Dashboard
                        @endif
                    </h1>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Theme Toggle -->
                    <div x-data="themeToggle">
                        <button @click="toggle()" class="btn-icon" title="Cambiar tema">
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

                    <!-- Notifications -->
                    <div x-data="{ 
                        open: false, 
                        count: 0, 
                        notifications: [],
                        async init() {
                            await this.fetchNotifications();
                            // Poll every 30 seconds
                            setInterval(() => this.fetchNotifications(), 30000);
                        },
                        async fetchNotifications() {
                            const response = await fetch('{{ route('notifications.index') }}');
                            const data = await response.json();
                            this.count = data.unread_count;
                            this.notifications = data.unread;
                        },
                        async markAsRead(id, url) {
                            await fetch(`/notifications/${id}/read`, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                }
                            });
                            this.count--;
                            this.notifications = this.notifications.filter(n => n.id !== id);
                            if (url) window.location.href = url;
                        },
                        async markAllRead() {
                           await fetch('{{ route('notifications.mark-all-read') }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                }
                            });
                            this.count = 0;
                            this.notifications = [];
                        }
                    }" class="relative">
                        <button @click="open = !open" class="btn-icon relative">
                            <svg class="w-5 h-5 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span x-show="count > 0" x-text="count"
                                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white"></span>
                        </button>

                        <div x-show="open" @click.away="open = false"
                            x-transition:enter="transition ease-out duration-100"
                            x-transition:enter-start="transform opacity-0 scale-95"
                            x-transition:enter-end="transform opacity-100 scale-100"
                            x-transition:leave="transition ease-in duration-75"
                            x-transition:leave-start="transform opacity-100 scale-100"
                            x-transition:leave-end="transform opacity-0 scale-95"
                            class="absolute right-0 z-50 mt-2 w-80 origin-top-right rounded-md bg-white dark:bg-gray-800 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
                            style="display: none;">

                            <div
                                class="p-3 border-b border-gray-100 dark:border-gray-700 flex justify-between items-center">
                                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Notificaciones</h3>
                                <button @click="markAllRead()"
                                    class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">Marcar
                                    todo leído</button>
                            </div>

                            <div class="max-h-96 overflow-y-auto">
                                <template x-for="notification in notifications" :key="notification.id">
                                    <div @click="markAsRead(notification.id, notification.data.action_url)"
                                        class="p-3 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-0">
                                        <div class="flex gap-3">
                                            <div class="flex-shrink-0 mt-1">
                                                <!-- Dynamic Icon based on type -->
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center"
                                                    :class="{
                                                        'bg-green-100 text-green-600': notification.data.type === 'success',
                                                        'bg-yellow-100 text-yellow-600': notification.data.type === 'warning',
                                                        'bg-blue-100 text-blue-600': notification.data.type === 'info',
                                                        'bg-gray-100 text-gray-600': !notification.data.type
                                                     }">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                        stroke="currentColor">
                                                        <path x-show="notification.data.icon === 'clipboard-check'"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                                        <path x-show="notification.data.icon === 'exclamation-circle'"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                        <path x-show="notification.data.icon === 'check-circle'"
                                                            stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                </div>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 dark:text-white"
                                                    x-text="notification.data.title"></p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2"
                                                    x-text="notification.data.message"></p>
                                                <p class="text-[10px] text-gray-400 mt-1"
                                                    x-text="notification.created_at"></p>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                                <div x-show="notifications.length === 0"
                                    class="p-6 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No tienes notificaciones
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="content-area animate-fade-in">
                {{ $slot }}
            </main>
        </div>
    </div>

    <style>
        [x-cloak] {
            display: none !important;
        }
    </style>
    @stack('scripts')
</body>

</html>