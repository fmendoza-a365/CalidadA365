<x-app-layout>
    <x-slot name="header">Editar Transcripción</x-slot>

    <div class="form-container">
        <div class="form-card">
            <div class="card-header flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-500/20 flex items-center justify-center">
                    <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">{{ $interaction->file_name }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">ID: {{ $interaction->id }}</p>
                </div>
            </div>
            <div class="form-body">
                <form method="POST" action="/transcripts/{{ $interaction->id }}" class="form-section">
                    @csrf
                    @method('PUT')

                    <div class="form-row">
                        <div class="form-group">
                            <label for="campaign_id" class="form-label">Campaña <span class="text-rose-500">*</span></label>
                            <select name="campaign_id" id="campaign_id" class="form-select" required>
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}" {{ old('campaign_id', $interaction->campaign_id) == $campaign->id ? 'selected' : '' }}>
                                        {{ $campaign->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('campaign_id')" class="mt-1" />
                        </div>

                        <div class="form-group">
                            <label for="agent_id" class="form-label">Asesor <span class="text-rose-500">*</span></label>
                            <select name="agent_id" id="agent_id" class="form-select" required>
                                @foreach($agents as $agent)
                                    <option value="{{ $agent->id }}" {{ old('agent_id', $interaction->agent_id) == $agent->id ? 'selected' : '' }}>
                                        {{ $agent->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('agent_id')" class="mt-1" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="occurred_at" class="form-label">Fecha y Hora <span class="text-rose-500">*</span></label>
                        <input type="datetime-local" name="occurred_at" id="occurred_at" 
                            value="{{ old('occurred_at', $interaction->occurred_at?->format('Y-m-d\TH:i')) }}" 
                            class="form-input" required>
                        <x-input-error :messages="$errors->get('occurred_at')" class="mt-1" />
                    </div>

                    <div class="form-group">
                        <label for="transcript_text" class="form-label">Contenido de la Transcripción</label>
                        <textarea name="transcript_text" id="transcript_text" rows="12" 
                            class="form-textarea font-mono text-sm">{{ old('transcript_text', $interaction->transcript_text) }}</textarea>
                        <x-input-error :messages="$errors->get('transcript_text')" class="mt-1" />
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('transcripts.show', $interaction->id) }}" class="btn-secondary btn-md">Cancelar</a>
                        <button type="submit" class="btn-primary btn-md">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
