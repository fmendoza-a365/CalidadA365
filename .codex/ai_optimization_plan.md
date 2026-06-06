# QA365 AI Token Optimization, Gemini Cache y TTS

## Summary

Implementar el alcance completo elegido: optimizaciones seguras de tokens, eliminacion de Golden Records del prompt, cache explicito Gemini y feedback de voz generado al publicar la evaluacion. No cambiar el flujo funcional de QA365: evaluacion, revision, publicacion y permisos deben seguir igual.

Referencias verificadas:

- Gemini context caching: https://ai.google.dev/gemini-api/docs/caching/
- Cloud Text-to-Speech `text:synthesize`: https://docs.cloud.google.com/text-to-speech/docs/reference/rest/v1/text/synthesize
- Gemini-TTS: https://docs.cloud.google.com/text-to-speech/docs/gemini-tts
- Gemini API speech generation: https://ai.google.dev/gemini-api/docs/speech-generation

## Key Changes

### Prompt y tokens

- Remover `getGoldenExamples()` y toda inyeccion de Golden Record del prompt.
- Subir `AiSettings::PROMPT_VERSION` a `quality-evaluation-v2`.
- Reordenar prompt: instrucciones, criterios, contexto operativo, reglas JSON, senales de audio, transcripcion.
- Usar JSON compacto sin `JSON_PRETTY_PRINT` para criterios y senales de audio.
- Usar leyendas con claves cortas en criterios: `id`, `a` categoria, `n` criterio, `d` descripcion, `w` peso, `mp` mala practica.
- Omitir campos vacios o placeholders como `Sin descripcion`.
- Normalizar contexto operativo sin borrar contenido: trim por linea y maximo dos lineas vacias consecutivas; mantener el limite actual de 30,000 caracteres.
- Compactar senales de audio con leyenda y conservar el limite actual de 12 segmentos para no cambiar cobertura funcional.
- Consolidar instrucciones duplicadas entre `systemInstruction` y prompt de usuario.

### Gemini cache

- Aplicar solo cuando `provider === 'gemini'`.
- Agregar columnas en `quality_form_versions`: `gemini_cache_id`, `gemini_cache_expires_at`, `gemini_cache_hash`, `gemini_cache_token_count`.
- Hash del contexto estatico debe incluir criterios, contexto operativo, modelo, system instruction y `PROMPT_VERSION`.
- Usar `countTokens`; minimos configurados por modelo segun docs actuales: `gemini-2.5-flash = 1024`, `gemini-2.5-pro = 4096`, `gemini-3-pro-preview = 4096`.
- No usar cache explicito para modelos desconocidos salvo minimo manual configurado.
- Usar `Cache::lock()` para evitar carreras entre workers.
- Si `cachedContent` falla por cache invalido, limpiar cache y reintentar una sola vez sin recursion.
- Guardar metadata de uso en audit event: cache usado, tokens cacheados, prompt tokens y output tokens cuando el proveedor los entregue.

### TTS al publicar

- Agregar campos en `evaluations`: `feedback_audio_path`, `feedback_audio_disk`, `feedback_audio_generated_at`, `feedback_audio_status`.
- Crear `GenerateFeedbackAudioJob`, despachado al publicar evaluacion si `AI_FEEDBACK_TTS_ENABLED=true`.
- Usar Cloud Text-to-Speech Gemini-TTS por defecto con OAuth Bearer token, no API key simple; permitir `AI_FEEDBACK_TTS_ACCESS_TOKEN` solo como override operativo/testeable.
- Agregar dependencia minima para OAuth si no existe: `google/auth`.
- Config defaults: `AI_FEEDBACK_TTS_ENABLED=false`, `AI_FEEDBACK_TTS_PROVIDER=google_cloud_tts`, `AI_FEEDBACK_TTS_MODEL=gemini-2.5-flash-tts`, `AI_FEEDBACK_TTS_VOICE=Orus`, `AI_FEEDBACK_TTS_LANGUAGE=es-419`, `AI_FEEDBACK_AUDIO_DISK=s3`.
- Truncar texto TTS por limite de API: prompt y texto deben respetar limites de bytes del modelo; si excede, recortar sin fallar publicacion.
- Guardar audio en disco configurable; usar ruta autenticada `evaluations/{evaluation}/feedback-audio` si el disco no soporta `temporaryUrl`.
- UI en `evaluations.show`: componente con `card` existente, visible solo con audio `ready` y usuario autorizado.

## Public Interfaces and Data

- Nuevas config/env:
  - `AI_GEMINI_CACHE_TTL=7200s`
  - `AI_FEEDBACK_TTS_ENABLED=false`
  - `AI_FEEDBACK_TTS_PROVIDER=google_cloud_tts`
  - `AI_FEEDBACK_TTS_MODEL=gemini-2.5-flash-tts`
  - `AI_FEEDBACK_TTS_VOICE=Orus`
  - `AI_FEEDBACK_TTS_LANGUAGE=es-419`
  - `AI_FEEDBACK_TTS_ACCESS_TOKEN=` (opcional)
  - `AI_FEEDBACK_AUDIO_DISK=s3`
- Nueva ruta autenticada:
  - `GET evaluations/{evaluation}/feedback-audio`
  - Autoriza con policy `view` sobre la evaluacion.
- Nuevos servicios:
  - `GeminiContextCacheService` para `countTokens`, creacion, validacion y limpieza de cache.
  - `FeedbackAudioService` para construir texto narrado, autenticar y llamar TTS.
- Mantener Golden Records en base/UI como dato historico, pero sin efecto en evaluaciones nuevas.

## Test Plan

- Ejecutar tests existentes de IA y luego suite completa:
  - `php artisan test tests/Feature/AiEvaluationQueueTest.php tests/Unit/AiJsonResponseParserTest.php tests/Unit/AiProviderErrorsTest.php tests/Feature/AiPerformanceDashboardTest.php`
  - `php artisan test`
- Agregar tests:
  - Prompt nuevo no contiene `GOLDEN RECORD` y si contiene reglas de no inferencia, limites de feedback y contexto operativo.
  - Prompt usa JSON compacto y omite placeholders vacios.
  - Simulated provider sigue evaluando sin cache ni TTS.
  - Gemini cache usa `Http::fake()`, respeta minimos por modelo, guarda columnas y metadata.
  - Cache invalido limpia columnas y reintenta una sola vez.
  - Publicar evaluacion despacha `GenerateFeedbackAudioJob` solo si TTS esta habilitado.
  - TTS con `Http::fake()` y `Storage::fake()` guarda audio y marca `ready`.
  - Falla TTS marca `failed` sin impedir publicacion.
  - Ruta de audio bloquea usuarios sin permiso y sirve audio a usuarios autorizados.

## Assumptions and Defaults

- Alcance elegido: todo completo.
- TTS se genera al publicar, no al terminar IA.
- TTS queda deshabilitado por defecto hasta configurar credenciales.
- Cloud Text-to-Speech es la opcion inicial porque entrega MP3 directo; Gemini API TTS queda fuera de v1 para evitar conversion PCM.
- No se elimina `is_gold` ni la UI existente de Golden Records en esta ejecucion.
- No se reduce el limite de contexto operativo de 30,000 caracteres en v1; solo se normaliza whitespace para reducir tokens sin perdida de contenido.
