<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            // Drop the existing unique constraint on interaction_id
            $table->dropUnique(['interaction_id']);
            
            // Add new columns
            $table->enum('type', ['ai', 'manual'])->default('ai')->after('interaction_id');
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->onDelete('set null')->after('agent_id');
            
            // Add new composite unique constraint
            $table->unique(['interaction_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropUnique(['interaction_id', 'type']);
            $table->dropColumn(['type', 'evaluator_id']);
            $table->unique('interaction_id');
        });
    }
};
