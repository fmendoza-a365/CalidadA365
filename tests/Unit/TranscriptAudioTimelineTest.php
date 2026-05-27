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
        $this->assertSame('mixto', $timeline['turns'][1]['sentiment']);
        $this->assertSame('00:20', $timeline['summary']['duration_label']);
        $this->assertGreaterThan(0, $timeline['summary']['handoffs']);
    }
}
