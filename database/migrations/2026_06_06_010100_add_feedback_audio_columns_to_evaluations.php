<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->string('feedback_audio_path')->nullable()->after('ai_feedback');
            $table->string('feedback_audio_disk')->nullable()->after('feedback_audio_path');
            $table->timestamp('feedback_audio_generated_at')->nullable()->after('feedback_audio_disk');
            $table->string('feedback_audio_status')->nullable()->after('feedback_audio_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn([
                'feedback_audio_path',
                'feedback_audio_disk',
                'feedback_audio_generated_at',
                'feedback_audio_status',
            ]);
        });
    }
};
