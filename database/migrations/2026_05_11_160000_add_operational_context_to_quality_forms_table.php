<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quality_forms', function (Blueprint $table) {
            $table->longText('operational_context_markdown')->nullable()->after('description');
            $table->string('context_file_path')->nullable()->after('operational_context_markdown');
            $table->string('context_file_original_name')->nullable()->after('context_file_path');
            $table->string('context_file_mime')->nullable()->after('context_file_original_name');
            $table->longText('context_file_text')->nullable()->after('context_file_mime');
            $table->timestamp('context_file_uploaded_at')->nullable()->after('context_file_text');
            $table->foreignId('context_file_uploaded_by')->nullable()->after('context_file_uploaded_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quality_forms', function (Blueprint $table) {
            $table->dropForeign(['context_file_uploaded_by']);
            $table->dropColumn([
                'operational_context_markdown',
                'context_file_path',
                'context_file_original_name',
                'context_file_mime',
                'context_file_text',
                'context_file_uploaded_at',
                'context_file_uploaded_by',
            ]);
        });
    }
};
