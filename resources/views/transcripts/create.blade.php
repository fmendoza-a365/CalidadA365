<x-app-layout>
    <x-slot name="header">Cargar Transcripción</x-slot>

    <div class="form-container">
        <div class="form-card">
            <div class="form-body">
                <form method="POST" action="{{ route('transcripts.store') }}" enctype="multipart/form-data"
                    class="form-section">
                    @csrf

                    <div class="form-row" x-data="{ 
                        selectedCampaign: '{{ old('campaign_id') }}', 
                        availableForms: [],
                        allForms: {{ json_encode($qualityForms) }},
                        selectedForm: '{{ old('quality_form_id') }}'
                    }" x-init="
                        if(selectedCampaign) { availableForms = allForms[selectedCampaign] || []; }
                        $watch('selectedCampaign', (val) => {
                            availableForms = allForms[val] || [];
                            selectedForm = '';
                        })
                    ">
                        <div class="form-group">
                            <label for="campaign_id" class="form-label">Campaña <span
                                    class="text-rose-500">*</span></label>
                            <select name="campaign_id" id="campaign_id" class="form-select" required
                                x-model="selectedCampaign">
                                <option value="">Seleccione una campaña</option>
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}">
                                        {{ $campaign->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('campaign_id')" class="mt-1" />
                        </div>

                        <div class="form-group">
                            <label for="quality_form_id" class="form-label">Ficha de Calidad (Opcional)</label>
                            <select name="quality_form_id" id="quality_form_id" class="form-select"
                                x-model="selectedForm">
                                <option value="">Usar ficha por defecto de la campaña</option>
                                <template x-for="form in availableForms" :key="form.id">
                                    <option :value="form.id" x-text="form.name"></option>
                                </template>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Si seleccionas una ficha específica, la IA usará esos
                                criterios. Si lo dejas vacío, usará la ficha activa de la campaña.</p>
                            <x-input-error :messages="$errors->get('quality_form_id')" class="mt-1" />
                        </div>

                        <div class="form-group">
                            <label for="agent_id" class="form-label">Asesor <span class="text-rose-500">*</span></label>
                            <select name="agent_id" id="agent_id" class="form-select" required>
                                <option value="">Seleccione un asesor</option>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ old('agent_id') == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('agent_id')" class="mt-1" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="occurred_at" class="form-label">Fecha y Hora de la Llamada <span
                                class="text-rose-500">*</span></label>
                        <input type="datetime-local" name="occurred_at" id="occurred_at"
                            value="{{ old('occurred_at') }}" class="form-input" required>
                        <x-input-error :messages="$errors->get('occurred_at')" class="mt-1" />
                    </div>

                    <div class="form-group" x-data="fileUploader()">
                        <label for="transcript_files" class="form-label">Archivos de Transcripción <span
                                class="text-rose-500">*</span></label>

                        <!-- Drop Zone -->
                        <div class="mt-2 border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer"
                            :class="{ 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20': isDragging, 'border-gray-300 dark:border-gray-600 hover:border-indigo-500 dark:hover:border-indigo-500': !isDragging }"
                            @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false"
                            @drop.prevent="handleDrop($event)" @click="$refs.fileInput.click()">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                            </svg>
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                <span class="font-semibold text-indigo-600 dark:text-indigo-400">Clic para
                                    seleccionar</span> o arrastra archivos aquí
                            </p>
                            <p class="mt-1 text-xs text-gray-500">Archivos .txt o audio (.mp3, .wav, .ogg, .m4a, .webm),
                                máximo 25MB</p>

                            <!-- Hidden Input -->
                            <input type="file" name="transcript_files[]" id="transcript_files" class="hidden"
                                accept=".txt,.mp3,.wav,.ogg,.m4a,.webm" multiple required x-ref="fileInput"
                                @change="handleFiles($event.target.files)">
                        </div>

                        <!-- Selected Files List -->
                        <div class="mt-4 space-y-2" x-show="files.length > 0">
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Archivos Seleccionados:
                            </h4>
                            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                                <template x-for="(file, index) in files" :key="index">
                                    <li class="flex items-center justify-between py-2 text-sm">
                                        <div class="flex items-center">
                                            <template x-if="isAudioFile(file.name)">
                                                <svg class="w-4 h-4 text-purple-500 mr-2" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                                                </svg>
                                            </template>
                                            <template x-if="!isAudioFile(file.name)">
                                                <svg class="w-4 h-4 text-gray-400 mr-2" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </template>
                                            <span class="text-gray-600 dark:text-gray-300" x-text="file.name"></span>
                                            <span class="ml-2 text-xs text-gray-400"
                                                x-text="formatSize(file.size)"></span>
                                            <template x-if="isAudioFile(file.name)">
                                                <span
                                                    class="ml-2 text-xs bg-purple-100 text-purple-700 dark:bg-purple-500/20 dark:text-purple-300 px-2 py-0.5 rounded-full">Audio</span>
                                            </template>
                                        </div>
                                        <button type="button" @click="removeFile(index)"
                                            class="text-rose-500 hover:text-rose-700">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>

                        <x-input-error :messages="$errors->get('transcript_files')" class="mt-1" />
                        @if($errors->has('transcript_files.*'))
                            <x-input-error :messages="collect($errors->get('transcript_files.*'))->flatten()->all()"
                                class="mt-1" />
                        @endif
                    </div>

                    <script>
                        function fileUploader() {
                            return {
                                isDragging: false,
                                files: [],

                                handleFiles(fileList) {
                                    // Convert FileList to Array and add to existing files
                                    // Note: We cannot easily modify the true value of file input for multiple additions
                                    // So we just clear and reset for simplicity in this basic version, or adhere to input behavior
                                    this.files = Array.from(fileList);
                                },

                                handleDrop(event) {
                                    this.isDragging = false;
                                    const droppedFiles = event.dataTransfer.files;
                                    if (droppedFiles.length > 0) {
                                        this.files = Array.from(droppedFiles);
                                        // Sync with input
                                        this.$refs.fileInput.files = droppedFiles;
                                    }
                                },

                                removeFile(index) {
                                    this.files.splice(index, 1);

                                    // Create a new DataTransfer to update the input
                                    const dt = new DataTransfer();
                                    this.files.forEach(file => dt.items.add(file));
                                    this.$refs.fileInput.files = dt.files;
                                },

                                formatSize(bytes) {
                                    if (bytes === 0) return '0 B';
                                    const k = 1024;
                                    const sizes = ['B', 'KB', 'MB', 'GB'];
                                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                                },

                                isAudioFile(name) {
                                    const ext = name.split('.').pop().toLowerCase();
                                    return ['mp3', 'wav', 'ogg', 'm4a', 'webm'].includes(ext);
                                }
                            }
                        }
                    </script>

                    <div class="alert alert-info">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Las transcripciones de texto serán evaluadas automáticamente por nuestra IA. Los archivos
                            de audio serán transcritos primero y luego evaluados.</span>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('transcripts.index') }}" class="btn-secondary btn-md">Cancelar</a>
                        <button type="submit" class="btn-primary btn-md">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                            Cargar y Evaluar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>