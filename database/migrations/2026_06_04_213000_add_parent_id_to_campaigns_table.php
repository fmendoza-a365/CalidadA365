<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('campaigns', 'parent_id')) {
                $table->foreignId('parent_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('campaigns')
                    ->nullOnDelete();
                $table->index(['parent_id', 'is_active'], 'campaigns_parent_active_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('campaigns', 'parent_id')) {
                $table->dropIndex('campaigns_parent_active_index');
                $table->dropConstrainedForeignId('parent_id');
            }
        });
    }
};
