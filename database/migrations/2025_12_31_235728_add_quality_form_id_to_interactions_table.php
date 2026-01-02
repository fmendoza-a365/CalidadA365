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
        Schema::table('interactions', function (Blueprint $table) {
            $table->foreignId('quality_form_id')->nullable()->after('campaign_id')->constrained('quality_forms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropForeign(['quality_form_id']);
            $table->dropColumn('quality_form_id');
        });
    }
};
