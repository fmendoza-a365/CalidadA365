<?php

namespace App\Console\Commands;

use App\Services\AIEvaluationService;
use Illuminate\Console\Command;

class EvaluateTranscripts extends Command
{
    protected $signature = 'qa:evaluate {--limit=10 : Número máximo de transcripciones a evaluar}';
    
    protected $description = 'Evalúa transcripciones pendientes usando IA';

    public function handle(AIEvaluationService $aiService): int
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Evaluando hasta {$limit} transcripciones pendientes...");
        
        $results = $aiService->evaluatePendingInteractions($limit);
        
        $this->newLine();
        $this->info("Resultados:");
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['✅ Exitosas', $results['success']],
                ['❌ Fallidas', $results['failed']],
                ['⏭️ Omitidas', $results['skipped']],
            ]
        );
        
        return Command::SUCCESS;
    }
}
