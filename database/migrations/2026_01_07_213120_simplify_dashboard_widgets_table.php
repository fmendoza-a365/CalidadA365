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
        Schema::dropIfExists('dashboard_widgets');

        Schema::create('dashboard_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('widget_type'); // stats_card, line_chart, bar_chart, table, pie_chart
            $table->string('title');
            $table->json('config')->nullable();
            
            // New simplified layout columns
            $table->string('width')->default('sm'); // sm, md, lg, full
            $table->integer('sort_order')->default(0);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dashboard_widgets', function (Blueprint $table) {
            $table->dropColumn(['width', 'sort_order']);
            
            // Re-add old columns
            $table->integer('x')->default(0);
            $table->integer('y')->default(0);
            $table->integer('w')->default(4);
            $table->integer('h')->default(4);
        });
    }
};
