<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->nullable()->constrained()->onDelete('cascade');
            $table->date('week_start');
            $table->date('week_end');
            $table->unsignedInteger('total_evaluations')->default(0);
            $table->decimal('average_score', 5, 2)->nullable();
            $table->json('top_failures')->nullable();
            $table->text('operational_insights')->nullable();
            $table->text('product_insights')->nullable();
            $table->text('recommendations')->nullable();
            $table->json('anonymized_quotes')->nullable();
            $table->string('generated_by', 50)->default('ai');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            
            $table->unique(['campaign_id', 'week_start']);
            $table->index('week_start');
            $table->index(['campaign_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reports');
    }
};
