@props([
    'name', 
    'title' => 'Confirmar acción', 
    'message' => '¿Estás seguro de que deseas continuar?',
    'confirmText' => 'Confirmar',
    'cancelText' => 'Cancelar',
    'type' => 'danger' 
])

<div
    x-data="{ show: false }"
    x-show="show"
    x-on:open-modal.window="if ($event.detail === '{{ $name }}') show = true"
    x-on:close-modal.window="if ($event.detail === '{{ $name }}') show = false"
    x-on:keydown.escape.window="show = false"
    style="display: none;"
    class="relative z-50"
    role="dialog" 
    aria-modal="true"
>
    <!-- Backdrop (Fondo Oscuro) -->
    <div x-show="show"
        x-transition:enter="ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-gray-900/70 backdrop-blur-sm transition-opacity"
        @click="show = false">
    </div>

    <!-- Contenedor del Modal -->
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            
            <div x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-xl bg-white dark:bg-gray-800 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-100 dark:border-gray-700"
                @click.stop
            >
                <!-- Cuerpo del Modal -->
                <div class="px-6 py-6 text-center">
                    <!-- Icono Círculo -->
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full mb-5
                        {{ $type === 'danger' ? 'bg-rose-50 text-rose-500 dark:bg-rose-900/20 dark:text-rose-400' : 
                          ($type === 'warning' ? 'bg-amber-50 text-amber-500 dark:bg-amber-900/20 dark:text-amber-400' : 
                           'bg-indigo-50 text-indigo-500 dark:bg-indigo-900/20 dark:text-indigo-400') }}">
                        
                        @if($type === 'danger')
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        @elseif($type === 'warning')
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        @else
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @endif
                    </div>

                    <!-- Título -->
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-2 font-inter" id="modal-title">
                        {{ $title }}
                    </h3>

                    <!-- Mensaje -->
                    <p class="text-sm text-gray-500 dark:text-gray-400 break-words whitespace-normal leading-relaxed mx-auto px-2">
                        {{ $message }}
                    </p>
                </div>

                <!-- Footer de Botones -->
                <div class="grid grid-cols-2 gap-3 px-6 pb-6 mt-2">
                    <button type="button" 
                        @click="show = false"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-gray-200 dark:focus:ring-gray-600 transition-colors">
                        {{ $cancelText }}
                    </button>

                    <button type="button" 
                        @click="$dispatch('confirm-action', { name: '{{ $name }}' }); show = false"
                        class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-lg border border-transparent text-sm font-semibold text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-1 transition-colors
                        {{ $type === 'danger' 
                            ? 'bg-rose-600 hover:bg-rose-700 focus:ring-rose-500' 
                            : ($type === 'warning' 
                                ? 'bg-amber-600 hover:bg-amber-700 focus:ring-amber-500' 
                                : 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500') 
                        }}">
                        {{ $confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
