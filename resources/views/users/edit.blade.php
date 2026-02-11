<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Editar Usuario: {{ $user->name }}
            </h2>
            <a href="{{ route('users.index') }}" class="btn-secondary btn-sm">Volver</a>
        </div>
    </x-slot>

    <div class="py-12" x-data="userForm()">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <form method="POST" action="{{ route('users.update', $user) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Sidebar / Photo -->
                    <div class="lg:col-span-1 space-y-6">
                        <!-- Profile Photo Card -->
                        <div class="card p-6 text-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Foto de Perfil</h3>
                            
                            <div class="relative inline-block group">
                                <div class="w-40 h-40 mx-auto rounded-full overflow-hidden bg-gray-100 dark:bg-gray-800 border-4 border-white dark:border-gray-700 shadow-xl relative">
                                    <div class="absolute inset-0 rounded-full {{ $user->frame_class }} pointer-events-none"></div>
                                    <img :src="photoPreview" class="w-full h-full object-cover">
                                </div>
                                <label for="profile_photo" class="absolute bottom-2 right-2 p-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full cursor-pointer shadow-lg transition-transform hover:scale-110 z-10">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <input type="file" id="profile_photo" name="profile_photo" class="hidden" accept="image/*" @change="updatePhotoPreview">
                                </label>
                            </div>
                            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                                Rol actual: <strong>{{ $user->roles->first()?->name }}</strong>
                            </p>
                        </div>

                        <!-- System Role Card -->
                        <div class="card p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                                <svg class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                                Acceso al Sistema
                            </h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="form-label">Rol de Usuario <span class="text-red-500">*</span></label>
                                    <select name="role" required class="form-select w-full bg-indigo-50/50 dark:bg-indigo-900/10 border-indigo-200 dark:border-indigo-800 focus:ring-indigo-500">
                                        <option value="">Seleccione un rol...</option>
                                        @foreach($roles as $role)
                                        <option value="{{ $role->name }}" {{ (old('role') ?? $user->roles->first()?->name) == $role->name ? 'selected' : '' }}>
                                            {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <input type="password" name="password" class="form-input w-full mb-1" placeholder="Dejar en blanco para mantener">
                                    <p class="text-xs text-gray-500 mb-3">Solo llenar si desea cambiarla.</p>
                                    
                                    <label class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" name="password_confirmation" class="form-input w-full" placeholder="Repetir contraseña">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Form Area -->
                    <div class="lg:col-span-2 space-y-6">
                        
                        <!-- Section: Identidad -->
                        <div class="card p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6 flex items-center gap-2 border-b border-gray-100 dark:border-gray-700 pb-2">
                                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </span>
                                Datos Personales
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="form-label">Nombre de Usuario (Login) <span class="text-red-500">*</span></label>
                                    <input type="text" name="username" value="{{ old('username', $user->username) }}" required class="form-input w-full font-mono bg-gray-50 dark:bg-gray-800">
                                </div>

                                <div>
                                    <label class="form-label">Nombres <span class="text-red-500">*</span></label>
                                    <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="form-input w-full">
                                </div>
                                
                                <div>
                                    <label class="form-label">Apellido Paterno <span class="text-red-500">*</span></label>
                                    <input type="text" name="paternal_surname" value="{{ old('paternal_surname', $user->paternal_surname) }}" required class="form-input w-full">
                                </div>

                                <div>
                                    <label class="form-label">Apellido Materno</label>
                                    <input type="text" name="maternal_surname" value="{{ old('maternal_surname', $user->maternal_surname) }}" class="form-input w-full">
                                </div>

                                <div>
                                    <label class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" name="birthdate" value="{{ old('birthdate', $user->birthdate?->format('Y-m-d')) }}" class="form-input w-full">
                                </div>

                                <div>
                                    <label class="form-label">Género</label>
                                    <select name="gender" class="form-select w-full">
                                        <option value="">Seleccione...</option>
                                        <option value="M" {{ old('gender', $user->gender) == 'M' ? 'selected' : '' }}>Masculino</option>
                                        <option value="F" {{ old('gender', $user->gender) == 'F' ? 'selected' : '' }}>Femenino</option>
                                        <option value="O" {{ old('gender', $user->gender) == 'O' ? 'selected' : '' }}>Otro</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Section: Contacto -->
                        <div class="card p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6 flex items-center gap-2 border-b border-gray-100 dark:border-gray-700 pb-2">
                                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-green-100 dark:bg-green-900/50 text-green-600 dark:text-green-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </span>
                                Información de Contacto
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="form-label">Email Empresa <span class="text-red-500">*</span></label>
                                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="form-input w-full bg-gray-50 dark:bg-gray-800">
                                </div>

                                <div>
                                    <label class="form-label">Email Personal</label>
                                    <input type="email" name="personal_email" value="{{ old('personal_email', $user->personal_email) }}" class="form-input w-full">
                                </div>

                                <div>
                                    <label class="form-label">Teléfono/WhatsApp Empresa</label>
                                    <input type="text" name="company_phone" value="{{ old('company_phone', $user->company_phone) }}" class="form-input w-full">
                                </div>

                                <div>
                                    <label class="form-label">Teléfono/WhatsApp Personal</label>
                                    <input type="text" name="personal_phone" value="{{ old('personal_phone', $user->personal_phone) }}" class="form-input w-full">
                                </div>

                                <div class="col-span-2">
                                    <label class="form-label">Dirección / Residencia</label>
                                    <input type="text" name="address" value="{{ old('address', $user->address) }}" class="form-input w-full">
                                </div>
                            </div>
                        </div>

                         <!-- Section: Geo -->
                        <div class="card p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-6 flex items-center gap-2 border-b border-gray-100 dark:border-gray-700 pb-2">
                                <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-orange-100 dark:bg-orange-900/50 text-orange-600 dark:text-orange-400">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </span>
                                Ubicación Geográfica (Perú)
                            </h3>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <label class="form-label">Departamento</label>
                                    <select x-model="selectedDept" name="department" class="form-select w-full">
                                        <option value="">Seleccione...</option>
                                        <template x-for="dept in Object.keys(geoData)" :key="dept">
                                            <option :value="dept" x-text="dept" :selected="dept == selectedDept"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Provincia</label>
                                    <select x-model="selectedProv" name="province" class="form-select w-full" :disabled="!selectedDept">
                                        <option value="">Seleccione...</option>
                                        <template x-for="prov in (selectedDept ? Object.keys(geoData[selectedDept]) : [])" :key="prov">
                                            <option :value="prov" x-text="prov" :selected="prov == selectedProv"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Distrito</label>
                                    <select x-model="selectedDist" name="district" class="form-select w-full" :disabled="!selectedProv">
                                        <option value="">Seleccione...</option>
                                        <template x-for="dist in (selectedProv ? geoData[selectedDept][selectedProv] : [])" :key="dist">
                                            <option :value="dist" x-text="dist" :selected="dist == selectedDist"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <!-- Form Actions -->
                    <div class="lg:col-span-3">
                         <div class="fixed bottom-0 left-0 right-0 p-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.1)] flex justify-end gap-3 z-40 lg:pl-72">
                             <a href="{{ route('users.index') }}" class="btn-secondary btn-md">Cancelar</a>
                             <button type="submit" class="btn-primary btn-md">Guardar Cambios</button>
                        </div>
                        <div class="h-16"></div> <!-- Spacer -->
                    </div>

                </div>
            </form>
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
                        // Only reset if it's a real change, not init
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
