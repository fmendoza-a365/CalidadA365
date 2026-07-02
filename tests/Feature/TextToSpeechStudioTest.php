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

    public function test_admin_can_open_tts_studio_without_changing_feedback_tts_config(): void
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
            ->assertSee('gemini-3.1-flash-tts-preview')
            ->assertSee('Charon')
            ->assertSee('LINEAR16');

        $this->assertSame('gemini-2.5-flash-tts', config('ai.feedback_tts.model'));
        $this->assertNull(Setting::get('ai.feedback_tts.model'));
    }

    public function test_admin_generates_google_tts_audio_and_downloads_it(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://texttospeech.googleapis.com/v1beta1/text:synthesize' => Http::response([
                'audioContent' => base64_encode('fake-wav-audio'),
            ]),
        ]);

        $admin = $this->userWithRole('admin');

        $response = $this->actingAs($admin)
            ->post(route('settings.tts.store'), [
                'intent' => 'generate',
                'endpoint' => 'https://texttospeech.googleapis.com/v1beta1/text:synthesize',
                'model_name' => 'gemini-3.1-flash-tts-preview',
                'language_code' => 'es-419',
                'voice_name' => 'Charon',
                'audio_encoding' => 'LINEAR16',
                'speaking_rate' => '1',
                'pitch' => '0',
                'style_instructions' => 'Habla con tono profesional y claro.',
                'access_token' => 'test-oauth-token',
                'credentials_path' => '',
                'tts_text' => 'Hola, este es un audio de prueba.',
            ]);

        $response->assertRedirect(route('settings.tts'));
        $response->assertSessionHas('tts_generated');

        $this->assertSame('gemini-3.1-flash-tts-preview', Setting::get('tts_studio.model_name'));
        $this->assertSame('Charon', Setting::get('tts_studio.voice_name'));
        $this->assertSame('LINEAR16', Setting::get('tts_studio.audio_encoding'));
        $this->assertSame('test-oauth-token', Setting::get('tts_studio.access_token'));
        $this->assertNull(Setting::get('ai.feedback_tts.model'));

        Http::assertSent(function ($request): bool {
            $data = $request->data();

            return $request->hasHeader('Authorization', 'Bearer test-oauth-token')
                && $request->url() === 'https://texttospeech.googleapis.com/v1beta1/text:synthesize'
                && ($data['voice']['modelName'] ?? null) === 'gemini-3.1-flash-tts-preview'
                && ($data['voice']['languageCode'] ?? null) === 'es-419'
                && ($data['voice']['name'] ?? null) === 'Charon'
                && ($data['audioConfig']['audioEncoding'] ?? null) === 'LINEAR16'
                && ($data['audioConfig']['pitch'] ?? null) === 0.0
                && ($data['audioConfig']['speakingRate'] ?? null) === 1.0
                && ! array_key_exists('prompt', $data['input'] ?? [])
                && ($data['input']['text'] ?? null) === "Habla con tono profesional y claro.\n\nHola, este es un audio de prueba.";
        });

        $generated = session('tts_generated');
        $this->assertNotEmpty($generated['download_url'] ?? null);

        $download = $this->actingAs($admin)->get($generated['download_url']);
        $download->assertOk();
        $this->assertSame('fake-wav-audio', $download->streamedContent());
        $this->assertStringContainsString('tts-charon-es-419-', $download->headers->get('content-disposition'));
        $this->assertStringContainsString('.wav', $download->headers->get('content-disposition'));
    }
}
