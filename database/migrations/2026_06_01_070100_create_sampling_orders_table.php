<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sampling_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sampling_plan_id')->constrained('sampling_plans')->cascadeOnDelete();
            $table->string('order_code')->unique();
            $table->date('week_start');
            $table->date('assigned_date');
            $table->string('assigned_day', 20);
            $table->string('advisor_code');
            $table->string('advisor_name');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supervisor_name')->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('campaign_name')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('quartile', 5);
            $table->unsignedInteger('required_by_week')->default(0);
            $table->string('rule_key', 60);
            $table->string('rule_name');
            $table->string('rule_params')->nullable();
            $table->text('instruction');
            $table->string('status', 30)->default('pending');
            $table->foreignId('evaluator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('evaluator_name')->nullable();
            $table->foreignId('interaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('call_identifier')->nullable();
            $table->string('reason')->nullable();
            $table->text('comment')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index(['sampling_plan_id', 'status'], 'sampling_orders_plan_status_idx');
            $table->index(['assigned_date', 'advisor_code'], 'sampling_orders_date_advisor_idx');
            $table->index(['campaign_id', 'assigned_date'], 'sampling_orders_campaign_date_idx');
            $table->index('agent_id', 'sampling_orders_agent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sampling_orders');
    }
};
