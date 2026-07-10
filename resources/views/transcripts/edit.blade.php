<x-app-layout>
    <x-slot name="header">Editar Interacción</x-slot>

    <div class="mx-auto max-w-7xl">
        <form method="POST" action="{{ route('transcripts.update', $interaction) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="card">
                <div class="card-header flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-500/20">
                        <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white">{{ $interaction->file_name }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">ID #{{ $interaction->id }}</p>
                    </div>
                </div>
                <div class="card-body space-y-6">
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                        <div class="form-group">
                            <label for="campaign_id" class="form-label">Campaña / Subcampaña <span class="text-rose-500">*</span></label>
                            <select name="campaign_id" id="campaign_id" class="form-select" required>
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}" {{ old('campaign_id', $interaction->campaign_id) == $campaign->id ? 'selected' : '' }}>
                                        {{ $campaign->displayName() }}
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
                                        {{ $agent->full_name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('agent_id')" class="mt-1" />
                        </div>

                        <div class="form-group">
                            <label for="occurred_at" class="form-label">Fecha y Hora de la Llamada <span class="text-rose-500">*</span></label>
                            <input type="datetime-local" name="occurred_at" id="occurred_at"
                                value="{{ old('occurred_at', $interaction->occurred_at?->format('Y-m-d\TH:i')) }}"
                                class="form-input" required>
                            <x-input-error :messages="$errors->get('occurred_at')" class="mt-1" />
                        </div>

                        <div class="form-group">
                            <label for="call_sn" class="form-label">SN / Código</label>
                            <input type="text" name="call_sn" id="call_sn"
                                value="{{ old('call_sn', $interaction->call_sn) }}" class="form-input font-mono"
                                maxlength="100" placeholder="SN-2026-000123">
                            <x-input-error :messages="$errors->get('call_sn')" class="mt-1" />
                        </div>
                    </div>
                </div>
            </div>

            @include('transcripts.partials.operational-fields', ['interaction' => $interaction])

            <div class="card">
                <div class="card-header">
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Contenido de la Transcripción</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <textarea name="transcript_text" id="transcript_text" rows="14"
                            class="form-textarea font-mono text-sm">{{ old('transcript_text', $interaction->transcript_text) }}</textarea>
                        <x-input-error :messages="$errors->get('transcript_text')" class="mt-1" />
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('transcripts.show', $interaction) }}" class="btn-secondary btn-md">Cancelar</a>
                <button type="submit" class="btn-primary btn-md">Guardar Cambios</button>
            </div>
        </form>
    </div>
</x-app-layout>
