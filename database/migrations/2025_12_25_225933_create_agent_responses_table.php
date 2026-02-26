<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->unique()->constrained()->onDelete('cascade');
            $table->foreignId('agent_id')->constrained('users')->onDelete('restrict');
            $table->enum('response_type', ['accept', 'dispute']);
            $table->text('commitment_comment')->nullable();
            $table->text('dispute_reason')->nullable();
            $table->json('disputed_items')->nullable();
            $table->timestamp('responded_at')->useCurrent();
            $table->timestamps();
            
            $table->index('response_type');
            $table->index('responded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_responses');
    }
};
