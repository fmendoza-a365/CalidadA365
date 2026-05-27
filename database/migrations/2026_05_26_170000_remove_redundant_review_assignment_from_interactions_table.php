<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('interactions', 'qa_monitor_id')) {
            $foreignKeyName = $this->foreignKeyNameForColumn('interactions', 'qa_monitor_id');
            $indexName = $this->indexNameForColumns('interactions', ['qa_monitor_id']);

            Schema::table('interactions', function (Blueprint $table) use ($foreignKeyName, $indexName) {
                if ($foreignKeyName) {
                    $table->dropForeign($foreignKeyName);
                }

                if ($indexName) {
                    $table->dropIndex($indexName);
                }

                $table->dropColumn('qa_monitor_id');
            });
        }

        Schema::table('interactions', function (Blueprint $table) {
            if (Schema::hasColumn('interactions', 'review_due_at')) {
                $table->dropColumn('review_due_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            if (! Schema::hasColumn('interactions', 'review_due_at')) {
                $table->date('review_due_at')->nullable()->after('priority');
            }

            if (! Schema::hasColumn('interactions', 'qa_monitor_id')) {
                $table->foreignId('qa_monitor_id')->nullable()->after('review_due_at')->constrained('users')->nullOnDelete();
                $table->index('qa_monitor_id', 'interactions_qa_monitor_id_index');
            }
        });
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

    /**
     * @param  array<int, string>  $columns
     */
    private function indexNameForColumns(string $table, array $columns): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['columns'] ?? []) === $columns) {
                return $index['name'] ?? null;
            }
        }

        return null;
    }
};
