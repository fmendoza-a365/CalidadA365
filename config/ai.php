<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Proveedor de IA por defecto
    |--------------------------------------------------------------------------
    |
    | Define qué proveedor de IA usar para las evaluaciones.
    | Opciones: 'openai', 'gemini', 'claude', 'simulated'
    |
    */
    'provider' => env('AI_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Configuración de OpenAI
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'temperature' => env('OPENAI_TEMPERATURE', 0.0),
        'max_tokens' => env('OPENAI_MAX_TOKENS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Google Gemini
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'temperature' => env('GEMINI_TEMPERATURE', 0.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Anthropic Claude
    |--------------------------------------------------------------------------
    */
    'claude' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-3-haiku-20240307'),
        'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 2000),
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
        'compliance_rate' => env('AI_SIMULATED_COMPLIANCE_RATE', 75), // % de cumplimiento
    ],

    /*
    |--------------------------------------------------------------------------
    | Speech-to-Text (Transcripción de Audio)
    |--------------------------------------------------------------------------
    |
    | Configuración para transcripción de audio a texto.
    | Usa el modelo y API key de Gemini configurados arriba.
    |
    */
    'stt' => [
        'provider' => 'gemini', // Uses Gemini multimodal capabilities
        'language' => env('STT_LANGUAGE', 'es'),
    ],

];
