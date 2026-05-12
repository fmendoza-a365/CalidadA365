<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('dispute_resolutions', function (Blueprint $table) {
            $table->string('status', 50)->default('pending_supervisor_review')->after('evaluation_id');
            $table->foreignId('supervisor_reviewed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('supervisor_reviewed_at')->nullable()->after('supervisor_reviewed_by');
            $table->text('supervisor_notes')->nullable()->after('supervisor_reviewed_at');
            $table->foreignId('qa_reviewed_by')->nullable()->after('supervisor_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('qa_reviewed_at')->nullable()->after('qa_reviewed_by');
            $table->string('qa_recommendation', 50)->nullable()->after('qa_reviewed_at');
            $table->text('qa_notes')->nullable()->after('qa_recommendation');
            $table->foreignId('coordinator_reviewed_by')->nullable()->after('qa_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('coordinator_reviewed_at')->nullable()->after('coordinator_reviewed_by');
            $table->string('coordinator_decision', 50)->nullable()->after('coordinator_reviewed_at');
            $table->text('coordinator_notes')->nullable()->after('coordinator_decision');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('dispute_resolutions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropForeign(['supervisor_reviewed_by']);
            $table->dropForeign(['qa_reviewed_by']);
            $table->dropForeign(['coordinator_reviewed_by']);
            $table->dropColumn([
                'status',
                'supervisor_reviewed_by',
                'supervisor_reviewed_at',
                'supervisor_notes',
                'qa_reviewed_by',
                'qa_reviewed_at',
                'qa_recommendation',
                'qa_notes',
                'coordinator_reviewed_by',
                'coordinator_reviewed_at',
                'coordinator_decision',
                'coordinator_notes',
            ]);
        });
    }
};
