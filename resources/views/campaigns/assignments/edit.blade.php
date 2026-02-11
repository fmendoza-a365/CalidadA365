<x-app-layout>
    <x-slot name="header">Editar Asignación</x-slot>

    <div class="form-container">
        <div class="form-card">
            <div class="card-header">
                <div class="flex items-center gap-3">
                    <div class="avatar avatar-md bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600">
                        {{ substr($assignment->agent->name, 0, 2) }}
                    </div>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white">{{ $assignment->agent->name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $campaign->name }}</p>
                    </div>
                </div>
            </div>
            <div class="form-body">
                <form method="POST" action="{{ route('assignments.update', $assignment) }}" class="form-section">
                    @csrf
                    @method('PUT')

                    <div class="form-group">
                        <label for="supervisor_id" class="form-label">Supervisor <span class="text-rose-500">*</span></label>
                        <select name="supervisor_id" id="supervisor_id" class="form-select" required>
                            <option value="">Seleccione un supervisor</option>
                            @foreach($supervisors as $supervisor)
                                <option value="{{ $supervisor->id }}" {{ old('supervisor_id', $assignment->supervisor_id) == $supervisor->id ? 'selected' : '' }}>
                                    {{ $supervisor->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('supervisor_id')" class="mt-1" />
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date" class="form-label">Fecha de Inicio</label>
                            <input type="date" name="start_date" id="start_date" 
                                value="{{ old('start_date', $assignment->start_date?->format('Y-m-d')) }}" class="form-input">
                            <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                        </div>

                        <div class="form-group">
                            <label for="end_date" class="form-label">Fecha de Fin</label>
                            <input type="date" name="end_date" id="end_date" 
                                value="{{ old('end_date', $assignment->end_date?->format('Y-m-d')) }}" class="form-input">
                            <x-input-error :messages="$errors->get('end_date')" class="mt-1" />
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="flex items-center gap-3 cursor-pointer">
                            <input type="checkbox" name="is_active" value="1" 
                                class="form-checkbox" {{ old('is_active', $assignment->is_active) ? 'checked' : '' }}>
                            <span class="text-sm text-gray-700 dark:text-gray-300">Asignación activa</span>
                        </label>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('campaigns.show', $campaign) }}" class="btn-secondary btn-md">Cancelar</a>
                        <button type="submit" class="btn-primary btn-md">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
