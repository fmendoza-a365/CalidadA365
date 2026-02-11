<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->index(['campaign_id', 'created_at'], 'idx_evaluations_campaign');
            $table->index(['agent_id', 'status', 'created_at'], 'idx_evaluations_agent');
        });

        Schema::table('evaluation_items', function (Blueprint $table) {
            $table->index(['evaluation_id', 'status'], 'idx_evaluation_items');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'idx_notifications_user');
        });

        Schema::table('quality_subattributes', function (Blueprint $table) {
            $table->index(['attribute_id', 'is_critical'], 'idx_subattributes');
        });

        Schema::table('interactions', function (Blueprint $table) {
            $table->index(['campaign_id', 'created_at'], 'idx_interactions_campaign');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('idx_evaluations_campaign');
            $table->dropIndex('idx_evaluations_agent');
        });

        Schema::table('evaluation_items', function (Blueprint $table) {
            $table->dropIndex('idx_evaluation_items');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user');
        });

        Schema::table('quality_subattributes', function (Blueprint $table) {
            $table->dropIndex('idx_subattributes');
        });

        Schema::table('interactions', function (Blueprint $table) {
            $table->dropIndex('idx_interactions_campaign');
        });
    }
};
