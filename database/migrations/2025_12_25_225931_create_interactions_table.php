<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->onDelete('restrict');
            $table->foreignId('agent_id')->constrained('users')->onDelete('restrict');
            $table->foreignId('supervisor_id')->constrained('users')->onDelete('restrict');
            $table->dateTime('occurred_at');
            $table->timestamp('uploaded_at')->useCurrent();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->string('file_path', 500);
            $table->string('file_name');
            $table->longText('transcript_text');
            $table->enum('status', ['uploaded', 'queued', 'scoring', 'scored', 'published', 'closed'])->default('uploaded');
            $table->string('batch_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['campaign_id', 'occurred_at']);
            $table->index(['agent_id', 'occurred_at']);
            $table->index(['supervisor_id', 'occurred_at']);
            $table->index('status');
            $table->index('batch_id');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interactions');
    }
};
