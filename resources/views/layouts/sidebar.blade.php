<aside x-data="{ open: true }" :class="open ? 'w-72' : 'w-20'"
    class="sidebar-transition fixed inset-y-0 left-0 z-50 flex flex-col h-screen bg-white dark:bg-gray-900 border-r border-gray-200 dark:border-gray-800 shadow-lg lg:static"
    x-on:toggle-sidebar.window="open = !open">

    <!-- Logo Area -->
    <div class="flex items-center justify-center h-[70px] border-b border-gray-100 dark:border-gray-800 relative">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 overflow-hidden px-4 w-full">
            <div
                class="flex-shrink-0 w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-indigo-500/30 shadow-lg">
                <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </div>
            <span x-show="open" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-x-10" x-transition:enter-end="opacity-100 translate-x-0"
                class="font-outfit font-bold text-xl text-gray-900 dark:text-white whitespace-nowrap">
                QA Center
            </span>
        </a>
    </div>

    <!-- Navigation -->
    <nav class="flex-1 overflow-y-auto py-6 px-3 space-y-1">

        <!-- Menu Group: Principal -->
        <div class="mb-6">
            <p x-show="open"
                class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 transition-opacity duration-300">
                Principal
            </p>

            <x-nav-link-sidebar :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home"
                :open="true">
                Dashboard
            </x-nav-link-sidebar>
        </div>

        @if(auth()->user()->can('view_campaigns') || auth()->user()->can('view_quality_forms') || auth()->user()->can('view_transcripts'))
            <!-- Menu Group: Gestión -->
            <div class="mb-6">
                <p x-show="open"
                    class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 transition-opacity duration-300">
                    Gestión
                </p>

                @can('view_campaigns')
                    <x-nav-link-sidebar :href="route('campaigns.index')" :active="request()->routeIs('campaigns.*')"
                        icon="collection" :open="true">
                        Campañas
                    </x-nav-link-sidebar>
                @endcan

                @can('view_quality_forms')
                    <x-nav-link-sidebar :href="route('quality-forms.index')" :active="request()->routeIs('quality-forms.*')"
                        icon="clipboard-list" :open="true">
                        Fichas de Calidad
                    </x-nav-link-sidebar>
                @endcan

                <!-- Transcripciones moved here logically or checking where it was? -->
                <!-- Wait, in the file view, Transcripts was in 'Operación' group in line 67. The USER screenshot shows Transcripts in 'GESTIÓN'. -->
                <!-- The user screenshot shows: GESTIÓN -> Campañas, Fichas, Transcripciones. -->
                <!-- The file content shows: Operación -> Transcripciones. -->
                <!-- I should move Transcriptions to Gestión to match User Screenshot AND add Insights there too. -->

                <x-nav-link-sidebar :href="route('transcripts.index')" :active="request()->routeIs('transcripts.*')"
                    icon="document-text" :open="true">
                    Transcripciones
                </x-nav-link-sidebar>

                <x-nav-link-sidebar :href="route('insights.index')" :active="request()->routeIs('insights.*')"
                    icon="document-report" :open="true">
                    Insights IA
                </x-nav-link-sidebar>
            </div>
        @endif

        <!-- Menu Group: Operación -->
        <div class="mb-6">
            <p x-show="open"
                class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 transition-opacity duration-300">
                Operación
            </p>

            @can('view_evaluations')
                <x-nav-link-sidebar :href="route('evaluations.index')" :active="request()->routeIs('evaluations.*')"
                    icon="star" :open="true">
                    Evaluaciones
                </x-nav-link-sidebar>
            @endcan
        </div>

        @if(auth()->user()->hasRole('admin') || auth()->user()->can('view_users') || auth()->user()->can('manage_roles'))
            <!-- Menu Group: Administración -->
            <div class="mb-6">
                <p x-show="open"
                    class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 transition-opacity duration-300">
                    Administración
                </p>

                @if(auth()->user()->hasRole('admin') || auth()->user()->can('view_users'))
                    <x-nav-link-sidebar :href="route('users.index')" :active="request()->routeIs('users.*')" icon="users"
                        :open="true">
                        Usuarios
                    </x-nav-link-sidebar>
                @endif

                @if(auth()->user()->hasRole('admin') || auth()->user()->can('manage_roles'))
                    <x-nav-link-sidebar :href="route('roles.index')" :active="request()->routeIs('roles.*')" icon="shield-check"
                        :open="true">
                        Roles y Permisos
                    </x-nav-link-sidebar>
                @endif
            </div>
        @endif

        @if(auth()->user()->can('manage_ai_settings'))
            <!-- Menu Group: Configuración -->
            <div class="mb-6">
                <p x-show="open"
                    class="px-3 text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2 transition-opacity duration-300">
                    Configuración
                </p>

                @can('manage_ai_settings')
                    <x-nav-link-sidebar :href="route('settings.ai')" :active="request()->routeIs('settings.*')" icon="cog"
                        :open="true">
                        IA y Modelos
                    </x-nav-link-sidebar>
                @endcan
            </div>
        @endif

    </nav>

    <!-- User Profile & Collapse -->
    <div class="p-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/50">
        <div class="flex items-center gap-3">
            <div class="relative">
                <button type="button"
                    class="flex items-center max-w-xs rounded-full focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <div
                        class="h-10 w-10 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-700 dark:text-indigo-300 font-bold">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                </button>
                <!-- Status Dot -->
                <span
                    class="absolute bottom-0 right-0 block h-2.5 w-2.5 rounded-full ring-2 ring-white dark:ring-gray-900 bg-green-400"></span>
            </div>

            <div x-show="open" class="flex-1 min-w-0 transition-opacity duration-300">
                <p class="text-sm font-medium text-gray-900 dark:text-white truncate">
                    {{ auth()->user()->name }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                    {{ auth()->user()->email }}
                </p>
            </div>

            <button x-show="open" @click="$dispatch('toggle-sidebar')"
                class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                </svg>
            </button>
            <button x-show="!open" @click="$dispatch('toggle-sidebar')"
                class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                </svg>
            </button>
        </div>

        <!-- Logout Button Mini -->
        <div class="mt-3 flex justify-center" x-show="open">
            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <button type="submit"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2 text-xs font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/10 hover:bg-red-100 dark:hover:bg-red-900/20 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    Cerrar Sesión
                </button>
            </form>
        </div>
    </div>
</aside>