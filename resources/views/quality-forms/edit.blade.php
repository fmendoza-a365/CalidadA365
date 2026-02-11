<x-app-layout>
    <x-slot name="header">Editar Ficha de Calidad</x-slot>

    <div class="space-y-6">
        @if($errors->any())
            <div class="alert alert-danger">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Información Básica -->
        <div class="form-container">
            <div class="form-card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Información de la Ficha</h3>
                </div>
                <div class="form-body">
                    <form method="POST" action="{{ route('quality-forms.update', $qualityForm) }}" class="form-section">
                        @csrf
                        @method('PUT')

                        <div class="form-group">
                            <label for="name" class="form-label">Nombre <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name', $qualityForm->name) }}"
                                class="form-input" required>
                        </div>

                        <div class="form-group">
                            <label for="description" class="form-label">Descripción</label>
                            <textarea name="description" id="description" rows="3"
                                class="form-textarea">{{ old('description', $qualityForm->description) }}</textarea>
                        </div>

                        <div class="form-actions">
                            <a href="{{ route('quality-forms.show', $qualityForm) }}"
                                class="btn-secondary btn-md">Cancelar</a>
                            <button type="submit" class="btn-primary btn-md">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Gestión de Atributos -->
        <div class="card"
            x-data="attributeManager(@if($qualityForm->latestVersion) {{ $qualityForm->latestVersion->formAttributes->load('subAttributes') }} @else [] @endif)">
            <div class="card-header flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 dark:text-white">Criterios de Evaluación</h3>
                <div class="flex gap-2" x-data="{ showImportModal: false }">
                    <button @click="showImportModal = true" type="button" class="btn-secondary btn-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        Importar CSV
                    </button>
                    <button @click="addAttribute()" type="button" class="btn-primary btn-sm">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Agregar Atributo
                    </button>

                    <!-- Import Modal -->
                    <div x-show="showImportModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                        <div
                            class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                            <div class="fixed inset-0 transition-opacity" aria-hidden="true"
                                @click="showImportModal = false">
                                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                            </div>
                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen"
                                aria-hidden="true">&#8203;</span>
                            <div
                                class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                <form action="{{ route('quality-forms.import', $qualityForm) }}" method="POST"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4">
                                            Importar Criterios desde CSV
                                        </h3>
                                        <div class="mb-4">
                                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-2">
                                                Carga un archivo CSV para reemplazar automáticamente todos los criterios
                                                actuales.
                                            </p>
                                            <a href="{{ asset('modelo_importacion_fichas.csv') }}" download
                                                class="text-indigo-600 dark:text-indigo-400 text-sm hover:underline">
                                                Descargar Plantilla CSV
                                            </a>
                                        </div>
                                        <div class="mt-2">
                                            <input type="file" name="csv_file" accept=".csv,.txt" required
                                                class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400">
                                        </div>
                                    </div>
                                    <div
                                        class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button type="submit"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                            Importar
                                        </button>
                                        <button type="button" @click="showImportModal = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-6">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Los pesos de los atributos deben sumar 100% (excluyendo categorías MP). Los ítems marcados
                        como <strong>Crítico</strong> (Malas Prácticas) tienen peso 0% y anulan la evaluación si
                        fallan.</span>
                </div>

                <form method="POST" action="{{ route('quality-forms.update-attributes', $qualityForm) }}">
                    @csrf
                    @method('PUT')

                    <div class="space-y-6" id="attributes-container">
                        <template x-for="(attribute, index) in attributes" :key="index">
                            <div
                                class="border border-gray-200 dark:border-gray-700 rounded-xl p-4 transition-all duration-300 hover:shadow-md">
                                <div class="flex items-start gap-4 mb-4">
                                    <div class="flex-1">
                                        <label class="form-label">Nombre del Atributo</label>
                                        <input type="text" :name="`attributes[${index}][name]`" x-model="attribute.name"
                                            class="form-input" required>
                                    </div>
                                    <div class="w-24">
                                        <label class="form-label">Peso %</label>
                                        <input type="number" :name="`attributes[${index}][weight]`"
                                            x-model="attribute.weight" class="form-input" min="0" max="100" step="0.01"
                                            required>
                                    </div>
                                    <div class="pt-7">
                                        <button type="button" @click="removeAttribute(index)"
                                            class="text-rose-500 hover:text-rose-700">
                                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">Concepto</label>
                                    <textarea :name="`attributes[${index}][concept]`" rows="2"
                                        x-model="attribute.concept" class="form-textarea"></textarea>
                                </div>

                                <!-- Subatributos -->
                                <div class="mt-4 ml-4 space-y-3">
                                    <div class="flex items-center justify-between">
                                        <h5 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Subatributos
                                        </h5>
                                        <button type="button" @click="addSubAttribute(index)"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                            + Agregar Subatributo
                                        </button>
                                    </div>

                                    <template x-for="(subAttribute, subIndex) in attribute.sub_attributes"
                                        :key="subIndex">
                                        <div class="mb-4">
                                            <div
                                                class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                                <div class="flex-1">
                                                    <input type="text"
                                                        :name="`attributes[${index}][subattributes][${subIndex}][name]`"
                                                        x-model="subAttribute.name" class="form-input"
                                                        placeholder="Nombre del subatributo" required>
                                                </div>
                                                <div class="w-20">
                                                    <input type="number"
                                                        :name="`attributes[${index}][subattributes][${subIndex}][weight_percent]`"
                                                        x-model="subAttribute.weight_percent" class="form-input" min="0"
                                                        max="100" step="0.01" placeholder="%" required
                                                        :disabled="subAttribute.is_critical"
                                                        :class="subAttribute.is_critical ? 'bg-gray-100 dark:bg-gray-900 opacity-50' : ''">
                                                    <template x-if="subAttribute.is_critical">
                                                        <input type="hidden"
                                                            :name="`attributes[${index}][subattributes][${subIndex}][weight_percent]`"
                                                            value="0">
                                                    </template>
                                                </div>
                                                <div class="flex items-center gap-2 pt-2">
                                                    <label class="flex items-center gap-1 text-xs cursor-pointer"
                                                        :class="subAttribute.is_critical ? 'text-rose-600 font-bold' : 'text-gray-600 dark:text-gray-400'">
                                                        <input type="checkbox"
                                                            :name="`attributes[${index}][subattributes][${subIndex}][is_critical]`"
                                                            value="1" class="form-checkbox text-rose-600"
                                                            :checked="subAttribute.is_critical"
                                                            @change="toggleCritical(index, subIndex, $event.target.checked)">
                                                        <span
                                                            x-text="subAttribute.is_critical ? 'MP (0%)' : 'Crítico'"></span>
                                                    </label>
                                                    <button type="button" @click="removeSubAttribute(index, subIndex)"
                                                        class="text-rose-500 hover:text-rose-700 ml-2">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24"
                                                            stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="mt-2 pl-3 grid grid-cols-2 gap-3">
                                                <div>
                                                    <label
                                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Concepto</label>
                                                    <textarea
                                                        :name="`attributes[${index}][subattributes][${subIndex}][concept]`"
                                                        x-model="subAttribute.concept" rows="2"
                                                        class="form-textarea text-xs w-full"
                                                        placeholder="Descripción del ítem..."></textarea>
                                                </div>
                                                <div>
                                                    <label
                                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Guía
                                                        Referencia</label>
                                                    <textarea
                                                        :name="`attributes[${index}][subattributes][${subIndex}][guidelines]`"
                                                        x-model="subAttribute.guidelines" rows="2"
                                                        class="form-textarea text-xs w-full"
                                                        placeholder="Ayuda para el evaluador..."></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>

                        <div x-show="attributes.length === 0"
                            class="empty-state py-8 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
                            <p class="text-gray-500 dark:text-gray-400">No hay atributos definidos. Haz clic en "Agregar
                                Atributo" para comenzar.</p>
                        </div>
                    </div>

                    <div class="form-actions mt-6">
                        <button type="submit" class="btn-primary btn-md">Guardar Criterios</button>
                    </div>
                </form>
            </div>
        </div>

        <div>
            <a href="{{ route('quality-forms.show', $qualityForm) }}" class="btn-secondary btn-md">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Volver a la Ficha
            </a>
        </div>
    </div>

    <script>
        function attributeManager(initialAttributes = []) {
            return {
                attributes: initialAttributes || [],

                addAttribute() {
                    this.attributes.push({
                        name: '',
                        weight: 0,
                        concept: '',
                        sub_attributes: [
                            { name: '', weight_percent: 100, concept: '', guidelines: '', is_critical: false }
                        ]
                    });
                },

                removeAttribute(index) {
                    if (confirm('¿Estás seguro de eliminar este atributo?')) {
                        this.attributes.splice(index, 1);
                    }
                },

                addSubAttribute(attrIndex) {
                    this.attributes[attrIndex].sub_attributes.push({
                        name: '',
                        weight_percent: 0,
                        concept: '',
                        guidelines: '',
                        is_critical: false
                    });
                },

                removeSubAttribute(attrIndex, subIndex) {
                    this.attributes[attrIndex].sub_attributes.splice(subIndex, 1);
                },

                toggleCritical(attrIndex, subIndex, checked) {
                    const sub = this.attributes[attrIndex].sub_attributes[subIndex];
                    sub.is_critical = checked;
                    if (checked) {
                        sub.weight_percent = 0;
                    }
                }
            }
        }
    </script>
</x-app-layout>