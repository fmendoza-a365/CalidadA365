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
            if (!Schema::hasColumn('evaluations', 'ai_summary')) {
                $table->longText('ai_summary')->nullable()->after('status');
            }
            if (!Schema::hasColumn('evaluations', 'ai_prompt')) {
                $table->longText('ai_prompt')->nullable()->after('ai_summary');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn(['ai_summary', 'ai_prompt']);
        });
    }
};
