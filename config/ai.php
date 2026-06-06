<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Defaults estáticos
    |--------------------------------------------------------------------------
    |
    | La configuración activa de IA se administra desde el módulo "IA y Modelos"
    | y se lee desde la tabla settings. Este archivo solo conserva defaults
    | técnicos para arranque/fallback interno, sin credenciales.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Proveedor de IA por defecto
    |--------------------------------------------------------------------------
    |
    | Define qué proveedor usar si todavía no existe configuración guardada.
    | Opciones: 'openai', 'gemini', 'claude', 'simulated'
    |
    */
    'provider' => 'simulated',

    /*
    |--------------------------------------------------------------------------
    | Configuración de OpenAI
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key' => null,
        'model' => 'gpt-4o-mini',
        'temperature' => 0.0,
        'max_tokens' => 4000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Google Gemini
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key' => null,
        'model' => 'gemini-2.5-flash',
        'temperature' => 0.0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Gemini Context Caching
    |--------------------------------------------------------------------------
    */
    'gemini_cache' => [
        'ttl' => env('AI_GEMINI_CACHE_TTL', '7200s'),
        'minimum_tokens' => [
            'gemini-2.5-flash' => 1024,
            'gemini-2.5-pro' => 4096,
            'gemini-3-pro-preview' => 4096,
        ],
        'manual_minimum_tokens' => env('AI_GEMINI_CACHE_MANUAL_MIN_TOKENS'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feedback Text-to-Speech
    |--------------------------------------------------------------------------
    */
    'feedback_tts' => [
        'enabled' => env('AI_FEEDBACK_TTS_ENABLED', false),
        'provider' => env('AI_FEEDBACK_TTS_PROVIDER', 'google_cloud_tts'),
        'model' => env('AI_FEEDBACK_TTS_MODEL', 'gemini-2.5-flash-tts'),
        'voice' => env('AI_FEEDBACK_TTS_VOICE', 'Orus'),
        'language' => env('AI_FEEDBACK_TTS_LANGUAGE', 'es-419'),
        'audio_disk' => env('AI_FEEDBACK_AUDIO_DISK', 's3'),
        'endpoint' => env('AI_FEEDBACK_TTS_ENDPOINT', 'https://texttospeech.googleapis.com/v1beta1/text:synthesize'),
        'scope' => env('AI_FEEDBACK_TTS_SCOPE', 'https://www.googleapis.com/auth/cloud-platform'),
        'access_token' => env('AI_FEEDBACK_TTS_ACCESS_TOKEN'),
        'prompt' => env('AI_FEEDBACK_TTS_PROMPT', 'Lee el feedback en español latino con tono profesional, claro y cercano.'),
        'prompt_byte_limit' => (int) env('AI_FEEDBACK_TTS_PROMPT_BYTE_LIMIT', 900),
        'text_byte_limit' => (int) env('AI_FEEDBACK_TTS_TEXT_BYTE_LIMIT', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Anthropic Claude
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key' => null,
        'model' => 'claude-3-haiku-20240307',
        'temperature' => 0.0,
        'max_tokens' => 4000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Modo Simulado
    |--------------------------------------------------------------------------
    |
    | Cuando no hay API key configurada o el proveedor es 'simulated',
    | se generarán evaluaciones aleatorias para desarrollo/testing.
    |
    */
    'simulated' => [
        'compliance_rate' => 75, // % de cumplimiento
    ],

    /*
    |--------------------------------------------------------------------------
    | Speech-to-Text (Transcripción de Audio)
    |--------------------------------------------------------------------------
    |
    | Configuración para transcripción de audio a texto.
    | La API key y el modelo de Gemini se toman desde "IA y Modelos".
    |
    */
    'stt' => [
        'provider' => 'gemini', // Uses Gemini multimodal capabilities
        'language' => env('STT_LANGUAGE', 'es'),
    ],

];
