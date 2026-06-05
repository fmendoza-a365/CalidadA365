<x-app-layout>
    <x-slot name="header">Contexto Operativo - {{ $qualityForm->name }}</x-slot>

    <div class="space-y-6">
        <div class="card">
            <div class="card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900 dark:text-white">Contexto operativo completo</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ $qualityForm->campaign?->displayName() ?? 'Sin campaña' }} · usado por la IA al evaluar esta ficha.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('quality-forms.show', $qualityForm) }}" class="btn-secondary btn-sm">Volver a la ficha</a>
                    @can('edit_quality_forms')
                        <a href="{{ route('quality-forms.edit', $qualityForm) }}" class="btn-primary btn-sm">Editar contexto</a>
                    @endcan
                </div>
            </div>
            <div class="card-body">
                @if($qualityForm->operational_context_markdown)
                    <div class="operational-context-full prose prose-sm dark:prose-invert max-w-none">
                        {!! \Illuminate\Support\Str::markdown($qualityForm->operational_context_markdown) !!}
                    </div>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">Esta ficha no tiene contexto markdown configurado.</p>
                @endif
            </div>
        </div>

        @if($qualityForm->context_file_path)
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Documento adjunto</h3>
                </div>
                <div class="card-body flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="font-medium text-gray-900 dark:text-white">{{ $qualityForm->context_file_original_name }}</div>
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $qualityForm->context_file_mime ?: 'archivo' }}
                            @if($qualityForm->context_file_uploaded_at)
                                · cargado el {{ $qualityForm->context_file_uploaded_at->format('d/m/Y H:i') }}
                            @endif
                        </div>
                    </div>
                    <a href="{{ route('quality-forms.context.download', $qualityForm) }}" class="btn-secondary btn-sm">
                        Descargar documento
                    </a>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
