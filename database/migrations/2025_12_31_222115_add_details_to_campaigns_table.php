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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
            $table->string('color')->default('#4F46E5');
            $table->decimal('target_quality', 5, 2)->nullable();
            $table->integer('target_aht')->nullable();
            $table->string('type')->default('Inbound');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('script_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'logo_path', 'color', 'target_quality', 'target_aht', 
                'type', 'start_date', 'end_date', 'script_url'
            ]);
        });
    }
};
