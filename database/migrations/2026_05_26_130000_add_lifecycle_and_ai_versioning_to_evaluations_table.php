<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluations', function (Blueprint $table) {
            if (! Schema::hasColumn('evaluations', 'previous_status_before_close')) {
                $table->string('previous_status_before_close', 50)->nullable()->after('status');
            }

            if (! Schema::hasColumn('evaluations', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('previous_status_before_close');
            }

            if (! Schema::hasColumn('evaluations', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('evaluations', 'closure_reason')) {
                $table->text('closure_reason')->nullable()->after('closed_by');
            }

            if (! Schema::hasColumn('evaluations', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('closure_reason');
            }

            if (! Schema::hasColumn('evaluations', 'reopened_by')) {
                $table->foreignId('reopened_by')->nullable()->after('reopened_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('evaluations', 'ai_provider')) {
                $table->string('ai_provider', 50)->nullable()->after('ai_model');
            }

            if (! Schema::hasColumn('evaluations', 'ai_prompt_version')) {
                $table->string('ai_prompt_version', 50)->nullable()->after('ai_provider');
            }

            if (! Schema::hasColumn('evaluations', 'ai_prompt_hash')) {
                $table->string('ai_prompt_hash', 64)->nullable()->after('ai_prompt_version');
            }

            if (! Schema::hasColumn('evaluations', 'ai_settings_snapshot')) {
                $table->json('ai_settings_snapshot')->nullable()->after('ai_prompt_hash');
            }
        });

        Schema::table('evaluations', function (Blueprint $table) {
            if (
                Schema::hasColumn('evaluations', 'status')
                && Schema::hasColumn('evaluations', 'closed_at')
                && ! Schema::hasIndex('evaluations', ['status', 'closed_at'])
            ) {
                $table->index(['status', 'closed_at']);
            }

            if (Schema::hasColumn('evaluations', 'ai_provider') && ! Schema::hasIndex('evaluations', ['ai_provider'])) {
                $table->index('ai_provider');
            }
        });
    }

    public function down(): void
    {
        $closedByForeignKey = $this->foreignKeyNameForColumn('evaluations', 'closed_by');
        $reopenedByForeignKey = $this->foreignKeyNameForColumn('evaluations', 'reopened_by');
        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('evaluations', 'previous_status_before_close') ? 'previous_status_before_close' : null,
            Schema::hasColumn('evaluations', 'closed_at') ? 'closed_at' : null,
            Schema::hasColumn('evaluations', 'closed_by') ? 'closed_by' : null,
            Schema::hasColumn('evaluations', 'closure_reason') ? 'closure_reason' : null,
            Schema::hasColumn('evaluations', 'reopened_at') ? 'reopened_at' : null,
            Schema::hasColumn('evaluations', 'reopened_by') ? 'reopened_by' : null,
            Schema::hasColumn('evaluations', 'ai_provider') ? 'ai_provider' : null,
            Schema::hasColumn('evaluations', 'ai_prompt_version') ? 'ai_prompt_version' : null,
            Schema::hasColumn('evaluations', 'ai_prompt_hash') ? 'ai_prompt_hash' : null,
            Schema::hasColumn('evaluations', 'ai_settings_snapshot') ? 'ai_settings_snapshot' : null,
        ]));

        Schema::table('evaluations', function (Blueprint $table) {
            if (Schema::hasIndex('evaluations', ['status', 'closed_at'])) {
                $table->dropIndex(['status', 'closed_at']);
            }

            if (Schema::hasIndex('evaluations', ['ai_provider'])) {
                $table->dropIndex(['ai_provider']);
            }
        });

        Schema::table('evaluations', function (Blueprint $table) use ($closedByForeignKey, $reopenedByForeignKey) {
            if ($closedByForeignKey) {
                $table->dropForeign($closedByForeignKey);
            }

            if ($reopenedByForeignKey) {
                $table->dropForeign($reopenedByForeignKey);
            }
        });

        if ($columnsToDrop !== []) {
            Schema::table('evaluations', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    private function foreignKeyNameForColumn(string $table, string $column): ?string
    {
        foreach (Schema::getForeignKeys($table) as $foreignKey) {
            if (in_array($column, $foreignKey['columns'] ?? [], true)) {
                return $foreignKey['name'] ?? null;
            }
        }

        return null;
    }
};
