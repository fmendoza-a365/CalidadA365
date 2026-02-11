<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_id')->constrained()->onDelete('cascade');
            $table->foreignId('subattribute_id')->constrained('quality_subattributes')->onDelete('restrict');
            $table->enum('status', ['compliant', 'non_compliant', 'not_found', 'not_applicable']);
            $table->decimal('score', 5, 2);
            $table->decimal('max_score', 5, 2);
            $table->decimal('weighted_score', 5, 2);
            $table->text('evidence_quote')->nullable();
            $table->string('evidence_reference', 500)->nullable();
            $table->decimal('confidence', 3, 2)->nullable();
            $table->text('ai_notes')->nullable();
            $table->timestamps();
            
            $table->index('evaluation_id');
            $table->index('subattribute_id');
            $table->index('status');
            $table->index('confidence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_items');
    }
};
