<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUsersWithRoles;
use Tests\TestCase;

class TextToSpeechStudioTest extends TestCase
{
    use CreatesUsersWithRoles, RefreshDatabase;

    public function test_admin_can_open_simplified_tts_studio_without_changing_feedback_tts_config(): void
    {
        $admin = $this->userWithRole('admin');
        $agent = $this->userWithRole('agent');

        config(['ai.feedback_tts.model' => 'gemini-2.5-flash-tts']);

        $this->actingAs($agent)
            ->get(route('settings.tts'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('settings.tts'))
            ->assertOk()
            ->assertSee('Texto a Voz')
            ->assertSee('Generador de audio')
            ->assertSee('Español (Latinoamérica)')
            ->assertSee('Español (Perú)')
            ->assertSee('Charon - Informativa')
            ->assertSee('Sulafat - Cálida')
            ->assertSee('WAV')
            ->assertSee('Velocidad')
            ->assertSee('Pitch')
            ->assertSee('Generar audio')
            ->assertDontSee('Endpoint')
            ->assertDontSee('API key')
            ->assertDontSee('OAuth')
            ->assertDontSee('Credenciales')
            ->assertDontSee('Guardar configuración');

        $this->assertSame('gemini-2.5-flash-tts', config('ai.feedback_tts.model'));
        $this->assertNull(Setting::get('ai.feedback_tts.model'));
    }

    public function test_admin_generates_gemini_tts_audio_with_internal_configuration_and_downloads_it(): void
    {
        Storage::fake('local');
        Setting::set('ai.gemini_api_key', 'AIza-test-gemini-api-key', 'string', 'ai');

        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/interactions' => Http::response([
                'steps' => [
                    [
                        'content' => [
                            [
                                'mime_type' => 'audio/l16',
                                'data' => base64_encode('fake-pcm-audio'),
                                'channels' => 1,
                                'sample_rate' => 24000,
                                'type' => 'audio',
                            ],
                        ],
                        'type' => 'model_output',
                    ],
                ],
            ]),
        ]);

        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)
            ->post(route('settings.tts.store'), [
                'intent' => 'generate',
                'language_code' => 'es-419',
                'voice_name' => 'Charon',
                'audio_encoding' => 'LINEAR16',
                'speaking_rate' => '1',
                'pitch' => '0',
                'style_instructions' => 'Habla con tono profesional y claro.',
                'tts_text' => 'Hola, este es un audio de prueba.',
            ]);

        $response->assertRedirect(route('settings.tts'));
        $response->assertSessionHas('tts_generated');

        $this->assertSame('gemini_api_key', Setting::get('tts_studio.auth_mode'));
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/interactions', Setting::get('tts_studio.endpoint'));
        $this->assertSame('gemini-3.1-flash-tts-preview', Setting::get('tts_studio.model_name'));
        $this->assertSame('Español (Latinoamérica)', Setting::get('tts_studio.language_label'));
        $this->assertSame('Charon', Setting::get('tts_studio.voice_name'));
        $this->assertSame('LINEAR16', Setting::get('tts_studio.audio_encoding'));
        $this->assertNull(Setting::get('ai.feedback_tts.model'));

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->hasHeader('x-goog-api-key', 'AIza-test-gemini-api-key')
                && ! $request->hasHeader('Authorization')
                && $request->url() === 'https://generativelanguage.googleapis.com/v1beta/interactions'
                && ($data['model'] ?? null) === 'gemini-3.1-flash-tts-preview'
                && ($data['response_format']['type'] ?? null) === 'audio'
                && ($data['generation_config']['speech_config'][0]['voice'] ?? null) === 'Charon'
                && ($data['input'] ?? null) === "Lee en Español (Latinoamérica). Usa un ritmo natural. Usa un tono natural. Habla con tono profesional y claro.\n\nHola, este es un audio de prueba.";
        });

        $generated = session('tts_generated');
        $this->assertNotEmpty($generated['download_url'] ?? null);

        $download = $this->actingAs($admin)->get($generated['download_url']);
        $download->assertOk();
        $this->assertStringStartsWith('RIFF', $download->streamedContent());
        $this->assertStringContainsString('fake-pcm-audio', $download->streamedContent());
        $this->assertStringContainsString('tts-charon-es-419-', $download->headers->get('content-disposition'));
        $this->assertStringContainsString('.wav', $download->headers->get('content-disposition'));
    }

    public function test_generation_failure_hides_internal_configuration_details(): void
    {
        Http::fake();

        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)
            ->post(route('settings.tts.store'), [
                'intent' => 'generate',
                'language_code' => 'es-419',
                'voice_name' => 'Charon',
                'audio_encoding' => 'LINEAR16',
                'speaking_rate' => '1',
                'pitch' => '0',
                'style_instructions' => '',
                'tts_text' => 'Hola.',
            ]);

        $response->assertSessionHasErrors('tts_text');
        $this->assertSame(
            'No se pudo generar el audio con la configuración interna actual.',
            session('errors')->first('tts_text')
        );

        Http::assertNothingSent();
    }
}
