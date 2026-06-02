<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->foreignId('review_claimed_by')
                ->nullable()
                ->after('reanalysis_requested_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('review_claimed_at')->nullable()->after('review_claimed_by');
            $table->timestamp('review_claim_expires_at')->nullable()->after('review_claimed_at');
            $table->index(['review_claimed_by', 'review_claim_expires_at'], 'evaluations_review_claim_idx');
        });
    }

    public function down(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropIndex('evaluations_review_claim_idx');
            $table->dropForeign(['review_claimed_by']);
            $table->dropColumn(['review_claimed_by', 'review_claimed_at', 'review_claim_expires_at']);
        });
    }
};
