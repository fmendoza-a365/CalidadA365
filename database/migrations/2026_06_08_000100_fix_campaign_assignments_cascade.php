<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_user_assignments', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->change();
            $table->foreignId('supervisor_id')->nullable()->change();
        });

        // Drop existing foreign keys and recreate with SET NULL
        Schema::table('campaign_user_assignments', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['supervisor_id']);
        });

        Schema::table('campaign_user_assignments', function (Blueprint $table) {
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('supervisor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_user_assignments', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['supervisor_id']);
        });

        Schema::table('campaign_user_assignments', function (Blueprint $table) {
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('supervisor_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::table('campaign_user_assignments', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable(false)->change();
            $table->foreignId('supervisor_id')->nullable(false)->change();
        });
    }
};
