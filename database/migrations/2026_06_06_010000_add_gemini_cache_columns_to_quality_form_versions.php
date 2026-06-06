<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quality_form_versions', function (Blueprint $table) {
            $table->string('gemini_cache_id')->nullable()->after('published_by');
            $table->timestamp('gemini_cache_expires_at')->nullable()->after('gemini_cache_id');
            $table->string('gemini_cache_hash')->nullable()->after('gemini_cache_expires_at');
            $table->unsignedInteger('gemini_cache_token_count')->nullable()->after('gemini_cache_hash');
        });
    }

    public function down(): void
    {
        Schema::table('quality_form_versions', function (Blueprint $table) {
            $table->dropColumn([
                'gemini_cache_id',
                'gemini_cache_expires_at',
                'gemini_cache_hash',
                'gemini_cache_token_count',
            ]);
        });
    }
};
