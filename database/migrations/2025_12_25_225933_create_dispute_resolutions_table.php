<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispute_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_response_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('evaluation_id')->constrained()->onDelete('cascade');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('resolution_notes')->nullable();
            $table->enum('resolution_decision', ['upheld', 'overturned', 'partial'])->nullable();
            $table->decimal('adjusted_score', 5, 2)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            
            $table->index('resolved_by');
            $table->index('resolved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispute_resolutions');
    }
};
