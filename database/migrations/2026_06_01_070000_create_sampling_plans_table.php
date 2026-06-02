<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sampling_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->date('week_start');
            $table->date('week_end');
            $table->string('business_days', 20)->default('mon-fri');
            $table->time('start_hour')->default('09:00');
            $table->time('end_hour')->default('18:00');
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('campaign_filter')->nullable();
            $table->string('seed')->nullable();
            $table->json('quotas');
            $table->boolean('unique_day')->default(true);
            $table->boolean('rotate_methods')->default(true);
            $table->unsignedInteger('staff_count')->default(0);
            $table->unsignedInteger('orders_count')->default(0);
            $table->string('status', 30)->default('active');
            $table->text('staff_csv')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['week_start', 'status'], 'sampling_plans_week_status_idx');
            $table->index('campaign_id', 'sampling_plans_campaign_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sampling_plans');
    }
};
