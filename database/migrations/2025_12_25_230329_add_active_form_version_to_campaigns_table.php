<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->foreignId('active_form_version_id')
                ->nullable()
                ->after('description')
                ->constrained('quality_form_versions')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['active_form_version_id']);
            $table->dropColumn('active_form_version_id');
        });
    }
};
