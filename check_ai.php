<?php

use App\Services\AIEvaluationService;
use App\Models\Interaction;
use Illuminate\Support\Facades\Log;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "🔍 Verificando configuración...\n";
$service = new AIEvaluationService();
echo "Proveedor activo: " . $service->getProvider() . "\n";

// Buscar una interacción para probar
$interaction = Interaction::first();

if (!$interaction) {
    echo "❌ No hay interacciones para probar. Ejecuta los seeders primero.\n";
    exit(1);
}

// Limpiar evaluación previa si existe
if ($interaction->evaluation) {
    echo "🧹 Eliminando evaluación previa...\n";
    $interaction->evaluation->delete();
}

echo "📝 Probando con interacción ID: {$interaction->id}...\n";
echo "💬 Transcript: " . substr($interaction->transcript_text, 0, 50) . "...\n";

echo "🤖 Enviando a Gemini...\n";
$evaluation = $service->evaluateInteraction($interaction);

if ($evaluation) {
    echo "✅ ¡Éxito! Evaluación creada con ID: {$evaluation->id}\n";
    echo "📊 Puntaje: {$evaluation->percentage_score}%\n";
    echo "📄 Resumen IA: {$evaluation->ai_summary}\n";
} else {
    echo "❌ Falló la evaluación. Revisa el log.\n";
}
