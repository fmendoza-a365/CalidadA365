<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staffing_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('campaign_name')->nullable();
            $table->string('status', 30)->default('active');
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedInteger('active_count')->default(0);
            $table->string('source_filename')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'period_start']);
            $table->index('campaign_id');
        });

        Schema::create('staffing_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staffing_batch_id')->constrained('staffing_batches')->cascadeOnDelete();
            $table->string('employee_code', 80);
            $table->string('full_name', 180);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supervisor_code', 80)->nullable();
            $table->string('supervisor_name', 180)->nullable();
            $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('campaign_name')->nullable();
            $table->string('quartile', 5)->nullable();
            $table->string('status', 30)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['staffing_batch_id', 'employee_code'], 'staffing_member_batch_code_unique');
            $table->index(['campaign_id', 'status']);
            $table->index(['employee_code', 'status']);
            $table->index('user_id');
            $table->index('supervisor_id');
        });

        Schema::table('sampling_plans', function (Blueprint $table) {
            $table->foreignId('staffing_batch_id')
                ->nullable()
                ->after('campaign_id')
                ->constrained('staffing_batches')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sampling_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staffing_batch_id');
        });

        Schema::dropIfExists('staffing_members');
        Schema::dropIfExists('staffing_batches');
    }
};
