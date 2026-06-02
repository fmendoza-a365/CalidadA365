<?php

namespace Tests\Unit;

use App\Support\TranscriptAudioTimeline;
use App\Support\TranscriptConversationParser;
use PHPUnit\Framework\TestCase;

class TranscriptAudioTimelineTest extends TestCase
{
    public function test_it_builds_audio_segments_with_sentiment_metadata(): void
    {
        $turns = (new TranscriptConversationParser)->parse("[00:00] Agente: Hola\n[00:05] Cliente: Estoy preocupado\n[00:10] Agente: Lo resolvemos ahora");
        $timeline = (new TranscriptAudioTimeline)->build($turns, 20, [
            'sentiment' => ['overall' => 'positivo'],
            'sentiment_segments' => [
                ['index' => 1, 'sentiment' => 'mixto', 'emotion' => 'preocupacion', 'score' => -0.2, 'intensity' => 72],
            ],
        ]);

        $this->assertCount(3, $timeline['segments']);
        $this->assertCount(120, $timeline['bars']);
        $this->assertSame('preocupacion', $timeline['segments'][1]['emotion']);
        $this->assertSame('Preocupación', $timeline['segments'][1]['emotion_label']);
        $this->assertSame('question', $timeline['segments'][1]['emotion_icon']);
        $this->assertSame('mixto', $timeline['turns'][1]['sentiment']);
        $this->assertSame('00:20', $timeline['summary']['duration_label']);
        $this->assertGreaterThan(0, $timeline['summary']['handoffs']);
    }

    public function test_it_extends_emotional_timeline_to_full_audio_duration_when_timestamps_are_sparse(): void
    {
        $turns = (new TranscriptConversationParser)->parse("[00:00] Agente: Hola\nCliente: Tengo un problema\nAgente: Lo reviso\nCliente: Gracias");
        $timeline = (new TranscriptAudioTimeline)->build($turns, 120, [
            'sentiment' => ['overall' => 'mixto'],
        ]);

        $this->assertCount(4, $timeline['segments']);
        $this->assertSame(120, $timeline['segments'][3]['end']);
        $this->assertSame('02:00', $timeline['summary']['duration_label']);
        $this->assertGreaterThan(100, $this->coveredSeconds($timeline['segments']));
        $this->assertGreaterThan(0, $timeline['summary']['agent_talk_percent']);
        $this->assertGreaterThan(0, $timeline['summary']['client_talk_percent']);
    }

    public function test_it_scales_compressed_timestamps_across_real_audio_duration(): void
    {
        $turns = (new TranscriptConversationParser)->parse("[00:00] Agente: Hola\n[00:05] Cliente: Tengo un problema\n[00:10] Agente: Lo reviso\n[00:15] Cliente: Gracias");
        $timeline = (new TranscriptAudioTimeline)->build($turns, 120, [
            'sentiment' => ['overall' => 'mixto'],
        ]);

        $this->assertSame(120, $timeline['segments'][3]['end']);
        $this->assertGreaterThan(45, $timeline['segments'][2]['start']);
        $this->assertLessThan(70, $timeline['summary']['agent_talk_percent']);
        $this->assertGreaterThan(25, $timeline['summary']['client_talk_percent']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $segments
     */
    private function coveredSeconds(array $segments): int
    {
        return array_sum(array_map(
            fn (array $segment): int => max(0, (int) $segment['end'] - (int) $segment['start']),
            $segments
        ));
    }
}
