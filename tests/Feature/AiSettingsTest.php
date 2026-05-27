<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AIEvaluationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_settings_page_initializes_alpine_temperature_state_in_form_scope(): void
    {
        $admin = $this->adminUser();

        Setting::set('ai.provider', 'gemini', 'string', 'ai');
        Setting::set('ai.openai_temperature', 0.2, 'float', 'ai');
        Setting::set('ai.gemini_temperature', 0.4, 'float', 'ai');
        Setting::set('ai.claude_temperature', 0.1, 'float', 'ai');

        $response = $this->actingAs($admin)->get(route('settings.ai'));

        $response->assertOk();
        $response->assertSee('openai_temp:', false);
        $response->assertSee('gemini_temp:', false);
        $response->assertSee('claude_temp:', false);
        $response->assertDontSee('x-data="{ openai_temp', false);
        $response->assertDontSee('x-data="{ gemini_temp', false);
        $response->assertDontSee('x-data="{ claude_temp', false);
    }

    public function test_ai_settings_update_replaces_api_key_only_when_a_new_value_is_sent(): void
    {
        $admin = $this->adminUser();

        Setting::set('ai.gemini_api_key', 'old-key', 'string', 'ai');
        Setting::set('ai.provider', 'gemini', 'string', 'ai');

        $this->actingAs($admin)
            ->get(route('settings.ai'))
            ->assertOk()
            ->assertSee('API Key guardada')
            ->assertSee('•••• •••• •••• -key')
            ->assertDontSee('old-key');

        $this->actingAs($admin)
            ->post(route('settings.ai.update'), $this->payload([
                'provider' => 'gemini',
                'gemini_api_key' => '',
                'gemini_model' => 'gemini-2.5-flash',
            ]))
            ->assertRedirect(route('settings.ai'));

        $this->assertSame('old-key', Setting::get('ai.gemini_api_key'));
        $this->assertSame('gemini', Setting::get('ai.provider'));
        $this->assertSame('gemini-2.5-flash', Setting::get('ai.gemini_model'));

        $this->actingAs($admin)
            ->post(route('settings.ai.update'), $this->payload([
                'provider' => 'gemini',
                'gemini_api_key' => 'new-key',
            ]))
            ->assertRedirect(route('settings.ai'));

        $this->assertSame('new-key', Setting::get('ai.gemini_api_key'));
    }

    public function test_ai_provider_panels_do_not_use_layout_shifting_transitions(): void
    {
        $admin = $this->adminUser();

        $response = $this->actingAs($admin)->get(route('settings.ai'));

        $response->assertOk();
        $response->assertSee("x-show=\"provider === 'openai'\" x-cloak", false);
        $response->assertSee("x-show=\"provider === 'gemini'\" x-cloak", false);
        $response->assertSee("x-show=\"provider === 'claude'\" x-cloak", false);
        $response->assertSee("x-show=\"provider === 'simulated'\" x-cloak", false);
        $response->assertDontSee("x-show=\"provider === 'openai'\" x-transition", false);
        $response->assertDontSee("x-show=\"provider === 'gemini'\" x-transition", false);
        $response->assertDontSee("x-show=\"provider === 'claude'\" x-transition", false);
        $response->assertDontSee("x-show=\"provider === 'simulated'\" x-transition", false);
    }

    public function test_ai_settings_update_preserves_hidden_provider_fields(): void
    {
        $admin = $this->adminUser();

        Setting::set('ai.openai_model', 'custom-openai-model', 'string', 'ai');
        Setting::set('ai.openai_max_tokens', 1234, 'integer', 'ai');
        Setting::set('ai.gemini_api_key', 'gemini-key', 'string', 'ai');

        $this->actingAs($admin)
            ->post(route('settings.ai.update'), [
                'provider' => 'gemini',
                'gemini_model' => 'gemini-2.5-flash',
                'gemini_temperature' => 0.1,
            ])
            ->assertRedirect(route('settings.ai'));

        $this->assertSame('custom-openai-model', Setting::get('ai.openai_model'));
        $this->assertSame(1234, Setting::get('ai.openai_max_tokens'));
        $this->assertSame('gemini-2.5-flash', Setting::get('ai.gemini_model'));
        $this->assertSame(0.1, Setting::get('ai.gemini_temperature'));
    }

    public function test_real_provider_cannot_be_enabled_without_api_key(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->from(route('settings.ai'))
            ->post(route('settings.ai.update'), $this->payload([
                'provider' => 'openai',
                'openai_api_key' => '',
            ]))
            ->assertRedirect(route('settings.ai'))
            ->assertSessionHasErrors('provider');

        $this->assertNull(Setting::get('ai.provider'));
    }

    public function test_ai_evaluation_service_reads_database_settings_not_config_values(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.api_key' => 'env-like-key',
            'ai.gemini.model' => 'env-like-model',
        ]);

        $serviceWithoutSettings = new AIEvaluationService;

        $this->assertSame('simulated', $serviceWithoutSettings->getProvider());

        Setting::set('ai.provider', 'gemini', 'string', 'ai');
        Setting::set('ai.gemini_api_key', 'database-key', 'string', 'ai');
        Setting::set('ai.gemini_model', 'database-model', 'string', 'ai');
        Setting::set('ai.gemini_temperature', 0.7, 'float', 'ai');

        $serviceWithSettings = new AIEvaluationService;
        $config = $this->serviceConfig($serviceWithSettings);

        $this->assertSame('gemini', $serviceWithSettings->getProvider());
        $this->assertSame('database-key', $config['api_key']);
        $this->assertSame('database-model', $config['model']);
        $this->assertSame(0.7, $config['temperature']);
    }

    private function adminUser(): User
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'provider' => 'simulated',
            'openai_api_key' => '',
            'openai_model' => 'gpt-4o-mini',
            'openai_temperature' => 0,
            'openai_max_tokens' => 4000,
            'gemini_api_key' => '',
            'gemini_model' => 'gemini-2.5-flash',
            'gemini_temperature' => 0,
            'claude_api_key' => '',
            'claude_model' => 'claude-3-haiku-20240307',
            'claude_temperature' => 0,
            'claude_max_tokens' => 4000,
            'simulated_compliance_rate' => 75,
        ], $overrides);
    }

    private function serviceConfig(AIEvaluationService $service): array
    {
        $reflection = new ReflectionClass($service);
        $property = $reflection->getProperty('config');
        $property->setAccessible(true);

        return $property->getValue($service);
    }
}
