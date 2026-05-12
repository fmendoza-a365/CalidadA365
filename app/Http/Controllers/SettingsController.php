<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\AiSettings;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Página de configuración de IA
     */
    public function aiSettings()
    {
        if (! auth()->user()->hasRole('admin')) {
            abort(403, 'Acceso no autorizado.');
        }

        $currentProvider = AiSettings::provider();

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
                'configured' => AiSettings::isConfigured('openai'),
                'masked_key' => AiSettings::maskedApiKey('openai'),
            ],
            'gemini' => [
                'name' => 'Google Gemini',
                'description' => 'Usa Gemini Pro o Flash para evaluaciones rápidas y económicas.',
                'icon' => 'bolt',
                'configured' => AiSettings::isConfigured('gemini'),
                'masked_key' => AiSettings::maskedApiKey('gemini'),
            ],
            'claude' => [
                'name' => 'Anthropic Claude',
                'description' => 'Usa Claude para análisis detallados y contextuales.',
                'icon' => 'chat-bubble-left-right',
                'configured' => AiSettings::isConfigured('claude'),
                'masked_key' => AiSettings::maskedApiKey('claude'),
            ],
        ];

        $settings = [
            'provider' => $currentProvider,
            'openai_model' => AiSettings::get('openai_model'),
            'openai_temperature' => AiSettings::get('openai_temperature'),
            'openai_max_tokens' => AiSettings::get('openai_max_tokens'),
            'gemini_model' => AiSettings::get('gemini_model'),
            'gemini_temperature' => AiSettings::get('gemini_temperature'),
            'claude_model' => AiSettings::get('claude_model'),
            'claude_temperature' => AiSettings::get('claude_temperature'),
            'claude_max_tokens' => AiSettings::get('claude_max_tokens'),
            'simulated_compliance_rate' => AiSettings::get('simulated_compliance_rate'),
        ];

        return view('settings.ai', compact('providers', 'settings', 'currentProvider'));
    }

    /**
     * Guardar configuración de IA
     */
    public function updateAiSettings(Request $request)
    {
        if (! auth()->user()->hasRole('admin')) {
            abort(403, 'Acceso no autorizado.');
        }

        $validated = $request->validate([
            'provider' => 'required|in:simulated,openai,gemini,claude',
            'openai_api_key' => 'nullable|string|max:2000',
            'openai_model' => 'nullable|string|max:100',
            'openai_temperature' => 'nullable|numeric|min:0|max:2',
            'openai_max_tokens' => 'nullable|integer|min:1',
            'gemini_api_key' => 'nullable|string|max:2000',
            'gemini_model' => 'nullable|string|max:100',
            'gemini_temperature' => 'nullable|numeric|min:0|max:1',
            'claude_api_key' => 'nullable|string|max:2000',
            'claude_model' => 'nullable|string|max:100',
            'claude_temperature' => 'nullable|numeric|min:0|max:1',
            'claude_max_tokens' => 'nullable|integer|min:1',
            'simulated_compliance_rate' => 'nullable|integer|min:0|max:100',
        ]);

        $defaults = AiSettings::DEFAULTS;
        $selectedProvider = $validated['provider'];

        if (
            $selectedProvider !== 'simulated'
            && ! $request->filled("{$selectedProvider}_api_key")
            && ! AiSettings::isConfigured($selectedProvider)
        ) {
            return back()
                ->withErrors(['provider' => 'Para activar este proveedor debes guardar primero su API Key en IA y Modelos.'])
                ->withInput();
        }

        Setting::set('ai.provider', $validated['provider'], 'string', 'ai', 'Proveedor de IA activo');

        if ($request->filled('openai_api_key')) {
            Setting::set('ai.openai_api_key', $validated['openai_api_key'], 'string', 'ai', 'API Key de OpenAI');
        }
        if ($request->has('openai_model')) {
            Setting::set('ai.openai_model', $validated['openai_model'] ?? $defaults['openai_model'], 'string', 'ai', 'Modelo de OpenAI');
        }
        if ($request->has('openai_temperature')) {
            Setting::set('ai.openai_temperature', $validated['openai_temperature'] ?? $defaults['openai_temperature'], 'float', 'ai', 'Temperatura de OpenAI');
        }
        if ($request->has('openai_max_tokens')) {
            Setting::set('ai.openai_max_tokens', $validated['openai_max_tokens'] ?? $defaults['openai_max_tokens'], 'integer', 'ai', 'Max Tokens OpenAI');
        }

        if ($request->filled('gemini_api_key')) {
            Setting::set('ai.gemini_api_key', $validated['gemini_api_key'], 'string', 'ai', 'API Key de Gemini');
        }
        if ($request->has('gemini_model')) {
            Setting::set('ai.gemini_model', $validated['gemini_model'] ?? $defaults['gemini_model'], 'string', 'ai', 'Modelo de Gemini');
        }
        if ($request->has('gemini_temperature')) {
            Setting::set('ai.gemini_temperature', $validated['gemini_temperature'] ?? $defaults['gemini_temperature'], 'float', 'ai', 'Temperatura de Gemini');
        }

        if ($request->filled('claude_api_key')) {
            Setting::set('ai.claude_api_key', $validated['claude_api_key'], 'string', 'ai', 'API Key de Claude');
        }
        if ($request->has('claude_model')) {
            Setting::set('ai.claude_model', $validated['claude_model'] ?? $defaults['claude_model'], 'string', 'ai', 'Modelo de Claude');
        }
        if ($request->has('claude_temperature')) {
            Setting::set('ai.claude_temperature', $validated['claude_temperature'] ?? $defaults['claude_temperature'], 'float', 'ai', 'Temperatura de Claude');
        }
        if ($request->has('claude_max_tokens')) {
            Setting::set('ai.claude_max_tokens', $validated['claude_max_tokens'] ?? $defaults['claude_max_tokens'], 'integer', 'ai', 'Max Tokens Claude');
        }

        if ($request->has('simulated_compliance_rate')) {
            Setting::set('ai.simulated_compliance_rate', $validated['simulated_compliance_rate'] ?? $defaults['simulated_compliance_rate'], 'integer', 'ai', 'Tasa de cumplimiento simulado');
        }

        return redirect()->route('settings.ai')
            ->with('success', 'Configuración de IA actualizada correctamente.');
    }

    /**
     * Probar conexión con proveedor de IA
     */
    public function testAiConnection(Request $request)
    {
        if (! auth()->user()->hasRole('admin')) {
            abort(403, 'Acceso no autorizado.');
        }

        $provider = $request->input('provider', AiSettings::provider());

        try {
            if ($provider !== 'simulated' && ! AiSettings::isConfigured($provider)) {
                return response()->json([
                    'success' => false,
                    'provider' => $provider,
                    'message' => 'Falta configurar la API Key en IA y Modelos.',
                ], 422);
            }

            $result = [
                'success' => true,
                'provider' => $provider,
                'message' => $provider === 'simulated'
                    ? 'Modo simulado activo - no se requiere conexión externa.'
                    : "Configuración local de {$provider} lista para usarse.",
            ];

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'provider' => $provider,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
    }
}
