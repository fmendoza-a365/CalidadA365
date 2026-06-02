<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sampling_order_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sampling_order_id')->constrained('sampling_orders')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 60);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['sampling_order_id', 'occurred_at'], 'sampling_audit_order_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sampling_order_audit_events');
    }
};
