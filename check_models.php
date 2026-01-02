<?php

use Illuminate\Support\Facades\Http;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$apiKey = env('GEMINI_API_KEY');

if (empty($apiKey)) {
    die("❌ No GEMINI_API_KEY found in environment.\n");
}

echo "🔑 Using Key: " . substr($apiKey, 0, 8) . "...\n";

$url = "https://generativelanguage.googleapis.com/v1beta/models?key={$apiKey}";

echo "📡 Calling: $url\n";

try {
    $response = Http::get($url);
    
    if ($response->successful()) {
        echo "✅ Success! Available models:\n";
        $models = $response->json('models');
        if ($models) {
            foreach ($models as $model) {
                echo "- " . $model['name'] . "\n";
            }
        } else {
            echo "⚠️ No models found in response.\n";
            print_r($response->json());
        }
    } else {
        echo "❌ API Error: " . $response->status() . "\n";
        echo $response->body() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}
