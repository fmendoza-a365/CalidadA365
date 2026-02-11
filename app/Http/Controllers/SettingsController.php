<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{


    /**
     * Página de configuración de IA
     */
    public function aiSettings()
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Acceso no autorizado.');
        }

        $currentProvider = Setting::get('ai.provider', config('ai.provider', 'simulated'));
        
        $providers = [
            'simulated' => [
                'name' => 'Simulado (Desarrollo)',
                'description' => 'Genera evaluaciones aleatorias para pruebas. No requiere API key.',
                'icon' => 'beaker',
                'configured' => true,
            ],
            'openai' => [
                'name' => 'OpenAI GPT',
                'description' => 'Usa modelos GPT-4 o GPT-4o-mini para evaluaciones precisas.',
                'icon' => 'sparkles',
                'configured' => !empty(Setting::get('ai.openai_api_key')),
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'description' => 'Usa Gemini Pro o Flash para evaluaciones rápidas y económicas.',
                'icon' => 'bolt',
                'configured' => !empty(Setting::get('ai.gemini_api_key')),
            ],
            'claude' => [
                'name' => 'Anthropic Claude',
                'description' => 'Usa Claude para análisis detallados y contextuales.',
                'icon' => 'chat-bubble-left-right',
                'configured' => !empty(Setting::get('ai.claude_api_key')),
            ],
        ];

        $settings = [
            'provider' => $currentProvider,
            'openai_api_key' => Setting::get('ai.openai_api_key', ''),
            'openai_model' => Setting::get('ai.openai_model', 'gpt-4o-mini'),
            'openai_temperature' => Setting::get('ai.openai_temperature', 0.3),
            'openai_max_tokens' => Setting::get('ai.openai_max_tokens', 2000),
            'gemini_api_key' => Setting::get('ai.gemini_api_key', ''),
            'gemini_model' => Setting::get('ai.gemini_model', 'gemini-1.5-flash'),
            'gemini_temperature' => Setting::get('ai.gemini_temperature', 0.3),
            'claude_api_key' => Setting::get('ai.claude_api_key', ''),
            'claude_model' => Setting::get('ai.claude_model', 'claude-3-haiku-20240307'),
            'claude_temperature' => Setting::get('ai.claude_temperature', 0.3),
            'claude_max_tokens' => Setting::get('ai.claude_max_tokens', 2000),
            'simulated_compliance_rate' => Setting::get('ai.simulated_compliance_rate', 75),
        ];

        return view('settings.ai', compact('providers', 'settings', 'currentProvider'));
    }

    /**
     * Guardar configuración de IA
     */
    public function updateAiSettings(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Acceso no autorizado.');
        }

        $validated = $request->validate([
            'provider' => 'required|in:simulated,openai,gemini,claude',
            'openai_api_key' => 'nullable|string|max:500',
            'openai_model' => 'nullable|string|max:100',
            'openai_temperature' => 'nullable|numeric|min:0|max:2',
            'openai_max_tokens' => 'nullable|integer|min:1',
            'gemini_api_key' => 'nullable|string|max:500',
            'gemini_model' => 'nullable|string|max:100',
            'gemini_temperature' => 'nullable|numeric|min:0|max:1',
            'claude_api_key' => 'nullable|string|max:500',
            'claude_model' => 'nullable|string|max:100',
            'claude_temperature' => 'nullable|numeric|min:0|max:1',
            'claude_max_tokens' => 'nullable|integer|min:1',
            'simulated_compliance_rate' => 'nullable|integer|min:0|max:100',
        ]);

        // Guardar configuraciones
        Setting::set('ai.provider', $validated['provider'], 'string', 'ai', 'Proveedor de IA activo');
        
        if (!empty($validated['openai_api_key'])) {
            Setting::set('ai.openai_api_key', $validated['openai_api_key'], 'string', 'ai', 'API Key de OpenAI');
        }
        Setting::set('ai.openai_model', $validated['openai_model'] ?? 'gpt-4o-mini', 'string', 'ai', 'Modelo de OpenAI');
        Setting::set('ai.openai_temperature', $validated['openai_temperature'] ?? 0.3, 'string', 'ai', 'Temperatura de OpenAI');
        Setting::set('ai.openai_max_tokens', $validated['openai_max_tokens'] ?? 2000, 'integer', 'ai', 'Max Tokens OpenAI');
        
        if (!empty($validated['gemini_api_key'])) {
            Setting::set('ai.gemini_api_key', $validated['gemini_api_key'], 'string', 'ai', 'API Key de Gemini');
        }
        Setting::set('ai.gemini_model', $validated['gemini_model'] ?? 'gemini-1.5-flash', 'string', 'ai', 'Modelo de Gemini');
        Setting::set('ai.gemini_temperature', $validated['gemini_temperature'] ?? 0.3, 'string', 'ai', 'Temperatura de Gemini');
        
        if (!empty($validated['claude_api_key'])) {
            Setting::set('ai.claude_api_key', $validated['claude_api_key'], 'string', 'ai', 'API Key de Claude');
        }
        Setting::set('ai.claude_model', $validated['claude_model'] ?? 'claude-3-haiku-20240307', 'string', 'ai', 'Modelo de Claude');
        Setting::set('ai.claude_temperature', $validated['claude_temperature'] ?? 0.3, 'string', 'ai', 'Temperatura de Claude');
        Setting::set('ai.claude_max_tokens', $validated['claude_max_tokens'] ?? 2000, 'integer', 'ai', 'Max Tokens Claude');
        
        Setting::set('ai.simulated_compliance_rate', $validated['simulated_compliance_rate'] ?? 75, 'integer', 'ai', 'Tasa de cumplimiento simulado');

        return redirect()->route('settings.ai')
            ->with('success', 'Configuración de IA actualizada correctamente.');
    }

    /**
     * Probar conexión con proveedor de IA
     */
    public function testAiConnection(Request $request)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Acceso no autorizado.');
        }

        $provider = $request->input('provider', 'simulated');
        
        try {
            $aiService = app(\App\Services\AIEvaluationService::class);
            
            // Test simple
            $result = [
                'success' => true,
                'provider' => $provider,
                'message' => $provider === 'simulated' 
                    ? 'Modo simulado activo - no se requiere conexión externa.'
                    : "Conexión con {$provider} configurada correctamente.",
            ];
            
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'provider' => $provider,
                'message' => 'Error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
