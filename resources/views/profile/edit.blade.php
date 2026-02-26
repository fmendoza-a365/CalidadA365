<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Mi Perfil
        </h2>
    </x-slot>

    <div class="py-12" x-data="userForm()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <!-- Left Sidebar: Photo & Role -->
                <div class="lg:col-span-1 space-y-6">
                    <div
                        class="card p-6 text-center shadow-lg border border-gray-100 dark:border-gray-700/50 relative overflow-hidden">
                        <div
                            class="absolute top-0 left-0 w-full h-24 bg-gradient-to-r from-blue-500 to-indigo-600 opacity-10">
                        </div>

                        <div class="relative z-10">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6">Tu Avatar</h3>

                            <div class="relative inline-block px-4">
                                <div
                                    class="w-48 h-48 mx-auto rounded-full p-1 bg-white dark:bg-gray-800 shadow-2xl relative">
                                    <div class="w-full h-full rounded-full overflow-hidden relative">
                                        <!-- Frame -->
                                        <div
                                            class="absolute inset-0 z-20 pointer-events-none rounded-full {{ auth()->user()->frame_class }}">
                                        </div>
                                        <!-- Image -->
                                        <img :src="photoPreview"
                                            class="w-full h-full object-cover relative z-10 hover:scale-105 transition-transform duration-500">
                                    </div>

                                    <label for="profile_photo"
                                        class="absolute bottom-2 right-4 p-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full cursor-pointer shadow-lg hover:shadow-indigo-500/30 transition-all z-30 group">
                                        <svg class="w-5 h-5 group-hover:rotate-12 transition-transform" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <input type="file" id="profile_photo" form="profile-form" name="profile_photo"
                                            class="hidden" accept="image/*" @change="updatePhotoPreview">
                                    </label>
                                </div>
                            </div>

                            <div class="mt-6">
                                <span
                                    class="px-4 py-1.5 rounded-full text-sm font-semibold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-800">
                                    {{ ucfirst(auth()->user()->roles->first()->name ?? 'Usuario') }}
                                </span>
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400 max-w-xs mx-auto">
                                    Gestiona tu imagen y visualiza tu rango actual en el sistema.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Main Form -->
                <div class="lg:col-span-2 space-y-8">

                    <form id="profile-form" method="post" action="{{ route('profile.update') }}"
                        enctype="multipart/form-data">
                        @csrf
                        @method('patch')

                        <div class="space-y-6">
                            <!-- Datos Personales -->
                            <div
                                class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700/50">
                                <div
                                    class="card-header border-b border-gray-100 dark:border-gray-700/50 px-6 py-4 flex items-center gap-3 bg-gray-50/50 dark:bg-gray-800/50">
                                    <div
                                        class="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg text-blue-600 dark:text-blue-400">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <h3 class="font-bold text-gray-800 dark:text-gray-100">Datos Personales</h3>
                                </div>
                                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="form-label">Nombre de Usuario <span
                                                class="text-red-500">*</span></label>
                                        <input type="text" name="username"
                                            value="{{ old('username', $user->username) }}" required
                                            class="form-input w-full font-mono bg-gray-50 dark:bg-gray-900/50 border-gray-200 dark:border-gray-700">
                                        <x-input-error class="mt-2" :messages="$errors->get('username')" />
                                    </div>
                                    <div>
                                        <label class="form-label">Nombres <span class="text-red-500">*</span></label>
                                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required
                                            class="form-input w-full">
                                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                                    </div>
                                    <div>
                                        <label class="form-label">Apellido Paterno</label>
                                        <input type="text" name="paternal_surname"
                                            value="{{ old('paternal_surname', $user->paternal_surname) }}"
                                            class="form-input w-full">
                                    </div>
                                    <div>
                                        <label class="form-label">Apellido Materno</label>
                                        <input type="text" name="maternal_surname"
                                            value="{{ old('maternal_surname', $user->maternal_surname) }}"
                                            class="form-input w-full">
                                    </div>
                                    <div>
                                        <label class="form-label">Fecha de Nacimiento</label>
                                        <input type="date" name="birthdate"
                                            value="{{ old('birthdate', $user->birthdate?->format('Y-m-d')) }}"
                                            class="form-input w-full">
                                    </div>
                                    <div>
                                        <label class="form-label">Género</label>
                                        <select name="gender" class="form-select w-full">
                                            <option value="">Seleccione...</option>
                                            <option value="M" {{ old('gender', $user->gender) == 'M' ? 'selected' : '' }}>
                                                Masculino</option>
                                            <option value="F" {{ old('gender', $user->gender) == 'F' ? 'selected' : '' }}>
                                                Femenino</option>
                                            <option value="O" {{ old('gender', $user->gender) == 'O' ? 'selected' : '' }}>
                                                Otro</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Información de Contacto -->
                            <div
                                class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700/50">
                                <div
                                    class="card-header border-b border-gray-100 dark:border-gray-700/50 px-6 py-4 flex items-center gap-3 bg-gray-50/50 dark:bg-gray-800/50">
                                    <div
                                        class="p-2 bg-green-100 dark:bg-green-900/30 rounded-lg text-green-600 dark:text-green-400">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                    </div>
                                    <h3 class="font-bold text-gray-800 dark:text-gray-100">Contacto & Ubicación</h3>
                                </div>
                                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div
                                        class="col-span-1 md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-6 pb-6 border-b border-gray-100 dark:border-gray-700/50">
                                        <div>
                                            <label class="form-label">Email Empresa <span
                                                    class="text-red-500">*</span></label>
                                            <input type="email" name="email" value="{{ old('email', $user->email) }}"
                                                required class="form-input w-full bg-gray-50 dark:bg-gray-900/50">
                                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                                        </div>
                                        <div>
                                            <label class="form-label">Email Personal</label>
                                            <input type="email" name="personal_email"
                                                value="{{ old('personal_email', $user->personal_email) }}"
                                                class="form-input w-full">
                                        </div>
                                        <div>
                                            <label class="form-label">Teléfono Empresa</label>
                                            <input type="text" name="company_phone"
                                                value="{{ old('company_phone', $user->company_phone) }}"
                                                class="form-input w-full">
                                        </div>
                                        <div>
                                            <label class="form-label">Teléfono Personal</label>
                                            <input type="text" name="personal_phone"
                                                value="{{ old('personal_phone', $user->personal_phone) }}"
                                                class="form-input w-full">
                                        </div>
                                        <div class="col-span-1 md:col-span-2">
                                            <label class="form-label">Dirección</label>
                                            <input type="text" name="address"
                                                value="{{ old('address', $user->address) }}" class="form-input w-full">
                                        </div>
                                    </div>

                                    <!-- Geo Selectors (Inline) -->
                                    <div class="col-span-1 md:col-span-2 pt-2">
                                        <label
                                            class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-4 block">Ubicación
                                            Geográfica (Perú)</label>
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <label class="form-label text-xs">Departamento</label>
                                                <select x-model="selectedDept" name="department"
                                                    class="form-select w-full text-sm">
                                                    <option value="">Seleccione...</option>
                                                    <template x-for="dept in Object.keys(geoData)" :key="dept">
                                                        <option :value="dept" x-text="dept"
                                                            :selected="dept == '{{ old('department', $user->department) }}'">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label text-xs">Provincia</label>
                                                <select x-model="selectedProv" name="province"
                                                    class="form-select w-full text-sm" :disabled="!selectedDept">
                                                    <option value="">Seleccione...</option>
                                                    <template
                                                        x-for="prov in (selectedDept ? Object.keys(geoData[selectedDept]) : [])"
                                                        :key="prov">
                                                        <option :value="prov" x-text="prov"
                                                            :selected="prov == '{{ old('province', $user->province) }}'">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-label text-xs">Distrito</label>
                                                <select x-model="selectedDist" name="district"
                                                    class="form-select w-full text-sm" :disabled="!selectedProv">
                                                    <option value="">Seleccione...</option>
                                                    <template
                                                        x-for="dist in (selectedProv ? geoData[selectedDept][selectedProv] : [])"
                                                        :key="dist">
                                                        <option :value="dist" x-text="dist"
                                                            :selected="dist == '{{ old('district', $user->district) }}'">
                                                        </option>
                                                    </template>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Telegram Integration -->
                                    <div
                                        class="col-span-1 md:col-span-2 pt-6 border-t border-gray-100 dark:border-gray-700/50 mt-4">
                                        <div
                                            class="flex items-start gap-4 p-4 rounded-xl bg-blue-50/50 dark:bg-blue-900/10 border border-blue-100 dark:border-blue-800">
                                            <div class="p-2 bg-blue-500 rounded-lg text-white mt-1 shadow-sm">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                                    <path
                                                        d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06-.01.24-.02.38z" />
                                                </svg>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-1">
                                                    Notificaciones por Telegram</h4>
                                                <p
                                                    class="text-xs text-gray-500 dark:text-gray-400 mb-4 leading-relaxed">
                                                    Recibe alertas automáticas (1 segundo de desfase) cada vez que la
                                                    Inteligencia Artificial evalúe tus audios. Para obtener tu Chat ID,
                                                    escribe <code>@getmyid_bot</code> en Telegram.
                                                </p>
                                                <div class="w-full md:w-1/2">
                                                    <label class="form-label text-xs font-semibold">Telegram Chat
                                                        ID</label>
                                                    <input type="text" name="telegram_chat_id"
                                                        value="{{ old('telegram_chat_id', $user->telegram_chat_id) }}"
                                                        class="form-input w-full font-mono text-sm shadow-sm"
                                                        placeholder="Ej: 123456789">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Bar -->
                            <div class="flex items-center justify-end gap-4 pt-4">
                                @if (session('status') === 'profile-updated')
                                    <span x-data="{ show: true }" x-show="show" x-transition
                                        x-init="setTimeout(() => show = false, 3000)"
                                        class="text-sm font-medium text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        Cambios Guardados
                                    </span>
                                @endif
                                <button type="submit"
                                    class="btn-primary btn-lg shadow-lg hover:shadow-indigo-500/30 min-w-[200px]">
                                    Guardar Cambios
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Divider -->
                    <div class="border-t border-gray-200 dark:border-gray-700 py-4"></div>

                    <!-- Security Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Password -->
                        <div
                            class="card bg-white dark:bg-gray-800 shadow-sm border border-gray-100 dark:border-gray-700/50">
                            <div class="p-6">
                                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-2">Seguridad</h3>
                                <p class="text-sm text-gray-500 mb-6">Actualiza tu contraseña para mantener tu cuenta
                                    segura.</p>
                                @include('profile.partials.update-password-form')
                            </div>
                        </div>

                        <!-- Delete -->
                        <div
                            class="card bg-white dark:bg-gray-800 shadow-sm border border-rose-100 dark:border-rose-900/30">
                            <div class="p-6">
                                <h3 class="text-lg font-bold text-rose-600 dark:text-rose-400 mb-2">Zona de Peligro</h3>
                                <p class="text-sm text-gray-500 mb-6 font-medium">Esta acción eliminará permanentemente
                                    tu cuenta y datos.</p>
                                @include('profile.partials.delete-user-form')
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <x-geo-data-script />
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('userForm', () => ({
                    photoPreview: '{{ $user->avatar_url }}',

                    // Geo Data State
                    selectedDept: '{{ old('department', $user->department) }}',
                    selectedProv: '{{ old('province', $user->province) }}',
                    selectedDist: '{{ old('district', $user->district) }}',

                    // Initialize empty, load in init()
                    geoData: {},

                    updatePhotoPreview(event) {
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            this.photoPreview = e.target.result;
                        };
                        reader.readAsDataURL(event.target.files[0]);
                    },

                    init() {
                        // Load Geo Data safely
                        this.geoData = window.peruGeoData || {};

                        // Watchers to reset child fields
                        this.$watch('selectedDept', (val) => {
                            if (val !== '{{ $user->department }}' && val !== '{{ old('department') }}') {
                                this.selectedProv = '';
                                this.selectedDist = '';
                            }
                        });
                        this.$watch('selectedProv', (val) => {
                            if (val !== '{{ $user->province }}' && val !== '{{ old('province') }}') {
                                this.selectedDist = '';
                            }
                        });
                    }
                }))
            })
        </script>
    @endpush
</x-app-layout>