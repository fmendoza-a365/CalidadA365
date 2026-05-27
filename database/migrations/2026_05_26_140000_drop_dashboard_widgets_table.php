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
        if (Schema::hasTable('dashboard_widgets') && ! Schema::hasTable('legacy_dashboard_widgets')) {
            Schema::rename('dashboard_widgets', 'legacy_dashboard_widgets');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('legacy_dashboard_widgets') && ! Schema::hasTable('dashboard_widgets')) {
            Schema::rename('legacy_dashboard_widgets', 'dashboard_widgets');

            return;
        }

        if (Schema::hasTable('dashboard_widgets')) {
            return;
        }

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('widget_type');
            $table->string('title');
            $table->json('config')->nullable();
            $table->string('width')->default('sm');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }
};
