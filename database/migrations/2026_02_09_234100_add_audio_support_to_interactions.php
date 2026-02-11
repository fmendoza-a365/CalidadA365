<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->string('source_type', 20)->default('text')->after('file_name');
            $table->integer('audio_duration')->nullable()->after('source_type');
            $table->string('transcription_status', 20)->nullable()->after('audio_duration');
        });
    }

    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'audio_duration', 'transcription_status']);
        });
    }
};
