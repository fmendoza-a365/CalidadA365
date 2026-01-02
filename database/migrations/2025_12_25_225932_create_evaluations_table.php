<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interaction_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('form_version_id')->constrained('quality_form_versions')->onDelete('restrict');
            $table->foreignId('campaign_id')->constrained()->onDelete('restrict');
            $table->foreignId('agent_id')->constrained('users')->onDelete('restrict');
            $table->decimal('total_score', 5, 2)->nullable();
            $table->decimal('max_possible_score', 5, 2)->default(100.00);
            $table->decimal('percentage_score', 5, 2)->nullable();
            $table->enum('status', [
                'pending_ai',
                'ai_processing',
                'ai_done',
                'visible_to_agent',
                'agent_responded',
                'disputed',
                'resolved',
                'final'
            ])->default('pending_ai');
            $table->timestamp('ai_processed_at')->nullable();
            $table->timestamp('visible_to_agent_at')->nullable();
            $table->timestamp('agent_viewed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();
            
            $table->index(['campaign_id', 'created_at']);
            $table->index(['agent_id', 'created_at']);
            $table->index('status');
            $table->index('form_version_id');
            $table->index('percentage_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
