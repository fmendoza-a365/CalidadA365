<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">Crear Nuevo Usuario</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Alta manual de acceso y datos de contacto.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('users.index') }}" class="btn-secondary btn-sm">Volver</a>
                <a href="{{ route('users.import') }}" class="btn-secondary btn-sm">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a2 2 0 002 2h12a2 2 0 002-2v-1M12 4v10m0-10l4 4m-4-4L8 8" />
                    </svg>
                    Importación masiva
                </a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6" x-data="userForm()">
        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="font-semibold">Hay errores en el formulario.</div>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('users.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                <aside class="space-y-6 xl:sticky xl:top-24 xl:self-start">
                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold text-gray-900 dark:text-white">Foto de Perfil</h3>
                                <span class="badge badge-neutral">Opcional</span>
                            </div>
                        </div>
                        <div class="card-body text-center">
                            <div class="relative mx-auto inline-block group">
                                <div class="h-36 w-36 overflow-hidden rounded-full border-4 border-white bg-gray-100 shadow-xl ring-1 ring-gray-200 dark:border-gray-800 dark:bg-gray-900 dark:ring-gray-700">
                                    <img :src="photoPreview" class="h-full w-full object-cover" alt="Vista previa de foto de perfil">
                                </div>
                                <label for="profile_photo" class="absolute bottom-1 right-1 inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-indigo-600 text-white shadow-lg transition hover:bg-indigo-700 hover:scale-105 dark:bg-gray-100 dark:text-gray-900 dark:hover:bg-white">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <input type="file" id="profile_photo" name="profile_photo" class="hidden" accept="image/*" @change="updatePhotoPreview">
                                </label>
                            </div>
                            <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">JPG, PNG o GIF. Máx 2MB.</p>
                        </div>
                    </div>

                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                </span>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Acceso al Sistema</h3>
                            </div>
                        </div>
                        <div class="card-body space-y-4">
                            <div>
                                <label class="form-label">Rol de Usuario <span class="text-rose-500">*</span></label>
                                <select name="role" x-model="role" required class="form-select">
                                    <option value="">Seleccione un rol...</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->name }}">
                                            {{ ucfirst(str_replace('_', ' ', $role->name)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="grid grid-cols-1 gap-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <div>
                                    <label class="form-label">Contraseña <span class="text-rose-500">*</span></label>
                                    <input type="password" name="password" required class="form-input" placeholder="••••••••">
                                </div>
                                <div>
                                    <label class="form-label">Confirmar Contraseña <span class="text-rose-500">*</span></label>
                                    <input type="password" name="password_confirmation" required class="form-input" placeholder="••••••••">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="card overflow-hidden"
                        x-show="['qa_monitor', 'qa_coordinator', 'manager'].includes(role)"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                    >
                        <div class="card-header">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </span>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Campañas Asignadas</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="max-h-64 space-y-2 overflow-y-auto pr-1">
                                @foreach($campaigns as $campaign)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-gray-200 p-3 transition hover:border-indigo-200 hover:bg-indigo-50/40 dark:border-gray-800 dark:hover:border-gray-700 dark:hover:bg-gray-900">
                                        <input
                                            type="checkbox"
                                            name="campaign_ids[]"
                                            value="{{ $campaign->id }}"
                                            @checked(is_array(old('campaign_ids')) && in_array($campaign->id, old('campaign_ids')))
                                            class="form-checkbox h-5 w-5"
                                        >
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-medium text-gray-800 dark:text-gray-200">{{ $campaign->displayName() }}</span>
                                            <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $campaign->type }}</span>
                                        </span>
                                    </label>
                                @endforeach

                                @if($campaigns->isEmpty())
                                    <div class="empty-state py-6">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">No hay campañas activas disponibles.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </aside>

                <section class="space-y-6">
                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-500/10 dark:text-blue-300">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                    </svg>
                                </span>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Datos Personales</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <label class="form-label">Nombre de Usuario (Login) <span class="text-rose-500">*</span></label>
                                    <input type="text" name="username" value="{{ old('username') }}" required class="form-input font-mono" placeholder="jdoe">
                                </div>

                                <div>
                                    <label class="form-label">Nombres <span class="text-rose-500">*</span></label>
                                    <input type="text" name="name" value="{{ old('name') }}" required class="form-input" placeholder="Nombres del usuario">
                                </div>

                                <div>
                                    <label class="form-label">Apellido Paterno <span class="text-rose-500">*</span></label>
                                    <input type="text" name="paternal_surname" value="{{ old('paternal_surname') }}" required class="form-input" placeholder="Apellido Paterno">
                                </div>

                                <div>
                                    <label class="form-label">Apellido Materno</label>
                                    <input type="text" name="maternal_surname" value="{{ old('maternal_surname') }}" class="form-input" placeholder="Apellido Materno">
                                </div>

                                <div>
                                    <label class="form-label">Fecha de Nacimiento</label>
                                    <input type="date" name="birthdate" value="{{ old('birthdate') }}" class="form-input">
                                </div>

                                <div>
                                    <label class="form-label">Género</label>
                                    <select name="gender" class="form-select">
                                        <option value="">Seleccione...</option>
                                        <option value="M" @selected(old('gender') == 'M')>Masculino</option>
                                        <option value="F" @selected(old('gender') == 'F')>Femenino</option>
                                        <option value="O" @selected(old('gender') == 'O')>Otro</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                    </svg>
                                </span>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Información de Contacto</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <div>
                                    <label class="form-label">Email Empresa <span class="text-rose-500">*</span></label>
                                    <input type="email" name="email" value="{{ old('email') }}" required class="form-input" placeholder="usuario@empresa.com">
                                </div>

                                <div>
                                    <label class="form-label">Email Personal</label>
                                    <input type="email" name="personal_email" value="{{ old('personal_email') }}" class="form-input" placeholder="usuario@gmail.com">
                                </div>

                                <div>
                                    <label class="form-label">Teléfono/WhatsApp Empresa</label>
                                    <input type="text" name="company_phone" value="{{ old('company_phone') }}" class="form-input" placeholder="999 000 000">
                                </div>

                                <div>
                                    <label class="form-label">Teléfono/WhatsApp Personal</label>
                                    <input type="text" name="personal_phone" value="{{ old('personal_phone') }}" class="form-input" placeholder="999 000 000">
                                </div>

                                <div class="lg:col-span-2">
                                    <label class="form-label">Dirección / Residencia</label>
                                    <input type="text" name="address" value="{{ old('address') }}" class="form-input" placeholder="Av. Principal 123, Urb. Example">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card overflow-hidden">
                        <div class="card-header">
                            <div class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-100 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                </span>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Ubicación Geográfica (Perú)</h3>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <div>
                                    <label class="form-label">Departamento</label>
                                    <select x-model="selectedDept" name="department" class="form-select">
                                        <option value="">Seleccione...</option>
                                        <template x-for="dept in Object.keys(geoData)" :key="dept">
                                            <option :value="dept" x-text="dept" :selected="dept == '{{ old('department') }}'"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Provincia</label>
                                    <select x-model="selectedProv" name="province" class="form-select" :disabled="!selectedDept">
                                        <option value="">Seleccione...</option>
                                        <template x-for="prov in (selectedDept ? Object.keys(geoData[selectedDept]) : [])" :key="prov">
                                            <option :value="prov" x-text="prov" :selected="prov == '{{ old('province') }}'"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="form-label">Distrito</label>
                                    <select x-model="selectedDist" name="district" class="form-select" :disabled="!selectedProv">
                                        <option value="">Seleccione...</option>
                                        <template x-for="dist in (selectedProv ? geoData[selectedDept][selectedProv] : [])" :key="dist">
                                            <option :value="dist" x-text="dist" :selected="dist == '{{ old('district') }}'"></option>
                                        </template>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="sticky bottom-4 z-30 ml-auto flex w-full flex-col-reverse gap-2 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('users.index') }}" class="btn-secondary btn-md justify-center">Cancelar</a>
                <button type="submit" class="btn-primary btn-md justify-center">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Guardar Usuario
                </button>
            </div>
        </form>
    </div>

    @push('scripts')
    <x-geo-data-script />
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('userForm', () => ({
                photoPreview: 'https://ui-avatars.com/api/?name=Nuevo+Usuario&color=7F9CF5&background=EBF4FF',

                role: '{{ old('role') }}',
                selectedDept: '{{ old('department') }}',
                selectedProv: '{{ old('province') }}',
                selectedDist: '{{ old('district') }}',
                geoData: {},

                updatePhotoPreview(event) {
                    const file = event.target.files[0];
                    if (! file) {
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.photoPreview = e.target.result;
                    };
                    reader.readAsDataURL(file);
                },

                init() {
                   this.geoData = window.peruGeoData || {};

                   this.$watch('selectedDept', (val) => {
                       if(val !== '{{ old('department') }}') {
                           this.selectedProv = '';
                           this.selectedDist = '';
                       }
                   });

                    this.$watch('selectedProv', (val) => {
                       if(val !== '{{ old('province') }}') {
                           this.selectedDist = '';
                       }
                   });
                }
            }))
        })
    </script>
    @endpush
</x-app-layout>
