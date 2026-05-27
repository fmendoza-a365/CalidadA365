<?php

namespace Tests\Unit;

use App\Support\TranscriptConversationParser;
use PHPUnit\Framework\TestCase;

class TranscriptConversationParserTest extends TestCase
{
    public function test_it_splits_inline_agent_and_client_turns(): void
    {
        $turns = $this->parser()->parse('Agente: Buenos dias. Cliente: Gracias por la ayuda.');

        $this->assertCount(2, $turns);
        $this->assertSame('agent', $turns[0]['speaker']);
        $this->assertSame('Buenos dias.', $turns[0]['message']);
        $this->assertSame('client', $turns[1]['speaker']);
        $this->assertSame('Gracias por la ayuda.', $turns[1]['message']);
    }

    public function test_it_preserves_timestamps_from_audio_transcripts(): void
    {
        $turns = $this->parser()->parse("[00:00] Agente: Hola\n[00:05] Cliente: Necesito soporte");

        $this->assertCount(2, $turns);
        $this->assertSame('00:00', $turns[0]['timestamp']);
        $this->assertSame(0, $turns[0]['timestamp_seconds']);
        $this->assertSame('Hola', $turns[0]['message']);
        $this->assertSame('00:05', $turns[1]['timestamp']);
        $this->assertSame(5, $turns[1]['timestamp_seconds']);
        $this->assertSame('Necesito soporte', $turns[1]['message']);
    }

    public function test_it_extracts_transcript_from_json_payloads(): void
    {
        $payload = json_encode([
            'transcript' => '[00:00] Agente: Hola\\n[00:04] Cliente: Listo',
            'sentiment' => ['overall' => 'positivo'],
        ]);

        $turns = $this->parser()->parse($payload);

        $this->assertCount(2, $turns);
        $this->assertSame('agent', $turns[0]['speaker']);
        $this->assertSame('client', $turns[1]['speaker']);
        $this->assertSame('Listo', $turns[1]['message']);
    }

    public function test_it_keeps_unstructured_text_as_context(): void
    {
        $turns = $this->parser()->parse('Resumen sin etiquetas de hablante.');

        $this->assertCount(1, $turns);
        $this->assertSame('system', $turns[0]['speaker']);
        $this->assertSame('Contexto', $turns[0]['label']);
        $this->assertSame('Resumen sin etiquetas de hablante.', $turns[0]['message']);
    }

    private function parser(): TranscriptConversationParser
    {
        return new TranscriptConversationParser;
    }
}
