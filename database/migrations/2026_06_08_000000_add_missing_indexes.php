<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->index('type');
            $table->index('evaluator_id');
        });

        Schema::table('interactions', function (Blueprint $table) {
            $table->index('quality_form_id');
        });

        Schema::table('agent_responses', function (Blueprint $table) {
            $table->index('agent_id');
        });

        Schema::table('dispute_resolutions', function (Blueprint $table) {
            $table->index('evaluation_id');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['evaluator_id']);
        });

        Schema::table('interactions', function (Blueprint $table) {
            $table->dropIndex(['quality_form_id']);
        });

        Schema::table('agent_responses', function (Blueprint $table) {
            $table->dropIndex(['agent_id']);
        });

        Schema::table('dispute_resolutions', function (Blueprint $table) {
            $table->dropIndex(['evaluation_id']);
        });
    }
};
