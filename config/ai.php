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
