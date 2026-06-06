<x-app-layout>
    <x-slot name="header">Cargar Interacción</x-slot>

    <div class="mx-auto max-w-[1280px]">
        <form method="POST" action="{{ route('transcripts.store') }}" enctype="multipart/form-data"
            class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_300px]"
            x-data="{ submitting: false, fileCount: 0, fileSize: '0 B' }"
            @transcript-files-updated="fileCount = $event.detail.count; fileSize = $event.detail.size"
            @submit="if(submitting) { $event.preventDefault(); } else { submitting = true; }">
            @csrf

            <div class="space-y-4">
                <div class="card overflow-hidden">
                    <div class="card-header">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-white">Nueva interacción</h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Completa los datos mínimos y adjunta la evidencia.</p>
                            </div>
                            <span class="badge badge-danger w-fit">Requerido</span>
                        </div>
                    </div>

                    <div class="card-body space-y-7">
                        <div x-data="{
                            selectedParent: '{{ old('_parent_campaign_id', $selectedParentId) }}',
                            selectedCampaign: '{{ old('campaign_id') }}',
                            selectedForm: '{{ old('quality_form_id') }}',
                            selectedSupervisor: '',
                            selectedAgent: '{{ old('agent_id') }}',
                            subcampaignsByParent: @js($subcampaignsByParent),
                            allForms: @js($qualityForms),
                            allAgents: @js($agentsByCampaign),
                            allSupervisors: @js($supervisorsByCampaign),
                            availableSubcampaigns: [],
                            availableForms: [],
                            availableSupervisors: [],
                            availableAgents: [],
                            init() {
                                this.refreshSubcampaigns(false);
                                this.refreshDependents(false);

                                this.$watch('selectedParent', () => this.refreshSubcampaigns(true));
                                this.$watch('selectedCampaign', () => this.refreshDependents(true));
                                this.$watch('selectedSupervisor', () => {
                                    this.filterAgents();
                                    if (this.selectedAgent && !this.availableAgents.some((agent) => String(agent.id) === String(this.selectedAgent))) {
                                        this.selectedAgent = '';
                                    }
                                });
                            },
                            refreshSubcampaigns(resetSelection = true) {
                                this.availableSubcampaigns = this.subcampaignsByParent[this.selectedParent] || [];

                                if (resetSelection && !this.availableSubcampaigns.some((campaign) => String(campaign.id) === String(this.selectedCampaign))) {
                                    this.selectedCampaign = '';
                                }

                                this.refreshDependents(resetSelection);
                            },
                            refreshDependents(resetSelection = true) {
                                const key = String(this.selectedCampaign || '');
                                this.availableForms = this.allForms[key] || [];
                                this.availableSupervisors = this.allSupervisors[key] || [];

                                if (resetSelection) {
                                    this.selectedForm = '';
                                    this.selectedSupervisor = '';
                                    this.selectedAgent = '';
                                }

                                this.filterAgents();

                                if (this.selectedForm && !this.availableForms.some((form) => String(form.id) === String(this.selectedForm))) {
                                    this.selectedForm = '';
                                }
                            },
                            filterAgents() {
                                const key = String(this.selectedCampaign || '');
                                let agents = this.allAgents[key] || [];
                                if (this.selectedSupervisor) {
                                    agents = agents.filter(agent => String(agent.supervisor_id) === String(this.selectedSupervisor));
                                }
                                this.availableAgents = agents;
                            }
                        }">
                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 xl:grid-cols-5">
                                <div class="form-group">
                                    <label for="_parent_campaign_id" class="form-label">Campaña <span class="text-rose-500">*</span></label>
                                    <select name="_parent_campaign_id" id="_parent_campaign_id" class="form-select" required x-model="selectedParent">
                                        <option value="">Seleccione campaña</option>
                                        @foreach($parentCampaigns as $campaign)
                                            <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="campaign_id" class="form-label">Subcampaña <span class="text-rose-500">*</span></label>
                                    <select name="campaign_id" id="campaign_id" class="form-select" required x-model="selectedCampaign" :disabled="!selectedParent">
                                        <option value="">Seleccione subcampaña</option>
                                        <template x-for="campaign in availableSubcampaigns" :key="campaign.id">
                                            <option :value="campaign.id" x-text="campaign.name"></option>
                                        </template>
                                    </select>
                                    <p x-show="selectedParent && availableSubcampaigns.length === 0" x-cloak class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                        Esta campaña no tiene subcampañas operativas visibles.
                                    </p>
                                    <x-input-error :messages="$errors->get('campaign_id')" class="mt-1" />
                                </div>

                                <div class="form-group">
                                    <label for="quality_form_id" class="form-label">Ficha de calidad</label>
                                    <select name="quality_form_id" id="quality_form_id" class="form-select" x-model="selectedForm">
                                        <option value="">Ficha activa de la subcampaña</option>
                                        <template x-for="form in availableForms" :key="form.id">
                                            <option :value="form.id" x-text="form.name"></option>
                                        </template>
                                    </select>
                                    <x-input-error :messages="$errors->get('quality_form_id')" class="mt-1" />
                                </div>

                                <div class="form-group">
                                    <label for="supervisor_filter_id" class="form-label">Supervisor</label>
                                    <select id="supervisor_filter_id" class="form-select" x-model="selectedSupervisor" :disabled="!selectedCampaign">
                                        <option value="">Todos los supervisores</option>
                                        <template x-for="superv in availableSupervisors" :key="superv.id">
                                            <option :value="superv.id" x-text="superv.name"></option>
                                        </template>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="agent_id" class="form-label">Asesor <span class="text-rose-500">*</span></label>
                                    <select name="agent_id" id="agent_id" class="form-select" required x-model="selectedAgent" :disabled="!selectedCampaign">
                                        <option value="">Seleccione un asesor</option>
                                        <template x-for="agent in availableAgents" :key="agent.id">
                                            <option :value="agent.id" x-text="agent.name"></option>
                                        </template>
                                    </select>
                                    <p x-show="selectedCampaign && availableAgents.length === 0" x-cloak class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                        No hay asesores asignados a esta subcampaña o al supervisor seleccionado.
                                    </p>
                                    <x-input-error :messages="$errors->get('agent_id')" class="mt-1" />
                                </div>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="form-group">
                                    <label for="occurred_at" class="form-label">Fecha y hora <span class="text-rose-500">*</span></label>
                                    <input type="datetime-local" name="occurred_at" id="occurred_at"
                                        value="{{ old('occurred_at') }}" class="form-input" required>
                                    <x-input-error :messages="$errors->get('occurred_at')" class="mt-1" />
                                </div>

                                <div class="form-group">
                                    <label for="call_sn" class="form-label">SN / Código</label>
                                    <input type="text" name="call_sn" id="call_sn" value="{{ old('call_sn') }}"
                                        class="form-input font-mono" maxlength="100" placeholder="SN-2026-000123">
                                    <x-input-error :messages="$errors->get('call_sn')" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-6 dark:border-gray-800" x-data="fileUploader()">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-white">Archivo de evidencia</h4>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">TXT o audio, máximo 100MB.</p>
                                </div>
                                <span class="badge badge-neutral">TXT / audio</span>
                            </div>

                            <label for="transcript_files" class="sr-only">Archivos</label>
                            <div class="cursor-pointer rounded-xl border border-dashed px-5 py-6 text-center transition-colors"
                                :class="{ 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/20': isDragging, 'border-gray-300 dark:border-gray-600 hover:border-indigo-500 dark:hover:border-gray-400': !isDragging }"
                                @dragover.prevent="isDragging = true" @dragleave.prevent="isDragging = false"
                                @drop.prevent="handleDrop($event)" @click="$refs.fileInput.click()">
                                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-300">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                    <span class="font-semibold text-indigo-600 dark:text-indigo-400">Seleccionar archivo</span>
                                    o arrastrar aquí
                                </p>
                                <p class="mt-1 text-xs text-gray-500">.txt, .mp3, .wav, .ogg, .opus, .m4a, .mp4, .aac, .webm, .flac</p>

                                <input type="file" name="transcript_files[]" id="transcript_files" class="hidden"
                                    accept=".txt,.mp3,.wav,.ogg,.oga,.opus,.m4a,.mp4,.mpeg,.mpga,.aac,.webm,.flac"
                                    multiple required x-ref="fileInput" @change="handleFiles($event.target.files)">
                            </div>

                            <div class="mt-4" x-show="files.length > 0" x-cloak>
                                <div class="mb-2 flex items-center justify-between">
                                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Seleccionados</h4>
                                    <span class="badge badge-neutral" x-text="files.length + ' archivo(s)'"></span>
                                </div>
                                <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                                    <template x-for="(file, index) in files" :key="index">
                                        <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2">
                                                    <span class="h-2 w-2 rounded-full" :class="isAudioFile(file.name) ? 'bg-purple-500' : 'bg-gray-400'"></span>
                                                    <span class="truncate text-gray-700 dark:text-gray-200" x-text="file.name"></span>
                                                </div>
                                                <div class="mt-1 text-xs text-gray-400" x-text="formatSize(file.size)"></div>
                                            </div>
                                            <button type="button" @click.stop="removeFile(index)" class="btn-ghost btn-sm text-rose-600 hover:text-rose-700">
                                                Quitar
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <x-input-error :messages="$errors->get('transcript_files')" class="mt-3" />
                            @if($errors->has('transcript_files.*'))
                                <x-input-error :messages="collect($errors->get('transcript_files.*'))->flatten()->all()" class="mt-3" />
                            @endif
                        </div>
                    </div>
                </div>

                @include('transcripts.partials.operational-fields', ['interaction' => null])
            </div>

            <aside class="xl:sticky xl:top-24 xl:self-start">
                <div class="card">
                    <div class="card-body space-y-5">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Resumen de Carga</h3>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Validación rápida antes de procesar.</p>
                        </div>

                        <div class="grid grid-cols-2 gap-3 border-y border-gray-100 py-4 dark:border-gray-800">
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Archivos</div>
                                <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white" x-text="fileCount"></div>
                            </div>
                            <div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Tamaño</div>
                                <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-white" x-text="fileSize"></div>
                            </div>
                        </div>

                        <div class="space-y-2 text-xs text-gray-500 dark:text-gray-400">
                            <div class="flex items-center justify-between">
                                <span>Límite por archivo</span>
                                <span class="font-mono text-gray-700 dark:text-gray-300">100MB</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Proceso</span>
                                <span class="font-mono text-gray-700 dark:text-gray-300">Automático</span>
                            </div>
                        </div>

                        <div class="space-y-2 pt-1">
                            <button type="submit" class="btn-primary btn-md w-full" :disabled="submitting"
                                :class="{ 'opacity-50 cursor-not-allowed': submitting }">
                                <svg x-show="!submitting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                                <svg x-show="submitting" x-cloak class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="submitting ? 'Procesando...' : 'Cargar y Evaluar'"></span>
                            </button>
                            <a href="{{ route('transcripts.index') }}" class="btn-secondary btn-md w-full">Cancelar</a>
                        </div>
                    </div>
                </div>
            </aside>

            <template x-teleport="body">
                <div x-show="submitting" x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 backdrop-blur-none"
                    x-transition:enter-end="opacity-100 backdrop-blur-sm"
                    class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-gray-900/80 backdrop-blur-sm">
                    <div class="relative mb-8 h-28 w-28">
                        <div class="absolute inset-0 rounded-full bg-purple-500 opacity-20 animate-[ping_2s_cubic-bezier(0,0,0.2,1)_infinite]"></div>
                        <div class="absolute inset-2 rounded-full border-4 border-gray-700 border-t-purple-500 border-r-purple-500 animate-spin"></div>
                        <div class="absolute inset-4 flex items-center justify-center rounded-full border border-gray-700 bg-gray-800 shadow-[0_0_30px_rgba(168,85,247,0.5)]">
                            <svg class="h-9 w-9 animate-bounce text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-white">Procesando interacción</h2>
                    <p class="mt-3 text-center text-sm text-purple-200">Subiendo archivos y preparando la evaluación de calidad.</p>
                </div>
            </template>
        </form>
    </div>

    <script>
        function fileUploader() {
            return {
                isDragging: false,
                files: [],

                handleFiles(fileList) {
                    this.files = Array.from(fileList);
                    this.updateSummary();
                },

                handleDrop(event) {
                    this.isDragging = false;
                    const droppedFiles = event.dataTransfer.files;

                    if (droppedFiles.length > 0) {
                        this.files = Array.from(droppedFiles);
                        this.$refs.fileInput.files = droppedFiles;
                        this.updateSummary();
                    }
                },

                removeFile(index) {
                    this.files.splice(index, 1);

                    const dt = new DataTransfer();
                    this.files.forEach(file => dt.items.add(file));
                    this.$refs.fileInput.files = dt.files;
                    this.updateSummary();
                },

                updateSummary() {
                    const bytes = this.files.reduce((total, file) => total + file.size, 0);
                    this.$dispatch('transcript-files-updated', {
                        count: this.files.length,
                        size: this.formatSize(bytes),
                    });
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
                    return ['mp3', 'wav', 'ogg', 'oga', 'opus', 'm4a', 'mp4', 'mpeg', 'mpga', 'aac', 'webm', 'flac'].includes(ext);
                }
            }
        }
    </script>
</x-app-layout>
