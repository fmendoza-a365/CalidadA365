<x-app-layout>
    <x-slot name="header">Nueva Ficha de Calidad</x-slot>

    <div class="form-container">
        <div class="form-card">
            <div class="form-body">
                <form method="POST" action="{{ route('quality-forms.store') }}" class="form-section">
                    @csrf

                    <div class="form-group">
                        <label for="campaign_id" class="form-label">Campaña <span class="text-rose-500">*</span></label>
                        <select name="campaign_id" id="campaign_id" class="form-select" required>
                            <option value="">Seleccione una campaña</option>
                            @foreach($campaigns as $campaign)
                                <option value="{{ $campaign->id }}" {{ old('campaign_id') == $campaign->id ? 'selected' : '' }}>
                                    {{ $campaign->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('campaign_id')" class="mt-1" />
                    </div>

                    <div class="form-group">
                        <label for="name" class="form-label">Nombre de la Ficha <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" 
                            class="form-input" placeholder="Ej: Ficha de Atención Telefónica" required>
                        <x-input-error :messages="$errors->get('name')" class="mt-1" />
                    </div>

                    <div class="form-group">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea name="description" id="description" rows="3" 
                            class="form-textarea" placeholder="Describe el propósito de esta ficha de calidad">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>

                    <div class="alert alert-info">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Después de crear la ficha, podrás agregar los atributos y subatributos de evaluación.</span>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('quality-forms.index') }}" class="btn-secondary btn-md">Cancelar</a>
                        <button type="submit" class="btn-primary btn-md">Crear Ficha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
