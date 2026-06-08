<?php

namespace Tests\Unit;

use App\Services\AiResponseParser;
use Tests\TestCase;

class AiJsonResponseParserTest extends TestCase
{
    public function test_it_recovers_evaluation_items_from_malformed_provider_json(): void
    {
        $parser = new AiResponseParser;

        $payload = <<<'JSON'
{
    "items": [
        {
            "id": 42,
            "status": "non_compliant",
            "evidence_quote": "El cliente dice "no estoy conforme" y el asesor no profundiza",
            "confidence": 0.82,
            "notes": "La respuesta incluye "comillas" sin escape dentro del JSON"
        }
    ],
    "general_feedback": "Resumen: la atencion requiere refuerzo en escucha activa."
}
JSON;

        $result = $parser->parse($payload);

        $this->assertIsArray($result);
        $this->assertSame(42, $result['items'][0]['id']);
        $this->assertSame('non_compliant', $result['items'][0]['status']);
        $this->assertSame(0.82, $result['items'][0]['confidence']);
        $this->assertStringContainsString('no estoy conforme', $result['items'][0]['evidence_quote']);
        $this->assertStringContainsString('requiere refuerzo', $result['general_feedback']);
    }
}
