<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            if (! Schema::hasColumn('interactions', 'external_id')) {
                $table->string('external_id', 120)->nullable()->after('call_sn');
            }

            if (! Schema::hasColumn('interactions', 'channel')) {
                $table->string('channel', 30)->nullable()->after('source_type');
            }

            if (! Schema::hasColumn('interactions', 'direction')) {
                $table->string('direction', 20)->nullable()->after('channel');
            }

            if (! Schema::hasColumn('interactions', 'contact_reason')) {
                $table->string('contact_reason', 160)->nullable()->after('direction');
            }

            if (! Schema::hasColumn('interactions', 'outcome')) {
                $table->string('outcome', 40)->nullable()->after('contact_reason');
            }

            if (! Schema::hasColumn('interactions', 'customer_reference')) {
                $table->string('customer_reference', 120)->nullable()->after('outcome');
            }

            if (! Schema::hasColumn('interactions', 'queue_name')) {
                $table->string('queue_name', 120)->nullable()->after('customer_reference');
            }

            if (! Schema::hasColumn('interactions', 'product_name')) {
                $table->string('product_name', 120)->nullable()->after('queue_name');
            }

            if (! Schema::hasColumn('interactions', 'priority')) {
                $table->string('priority', 20)->nullable()->after('product_name');
            }
        });

        Schema::table('interactions', function (Blueprint $table) {
            if (Schema::hasColumn('interactions', 'external_id') && ! Schema::hasIndex('interactions', 'interactions_external_id_index')) {
                $table->index('external_id', 'interactions_external_id_index');
            }

            if (
                Schema::hasColumn('interactions', 'channel')
                && Schema::hasColumn('interactions', 'occurred_at')
                && ! Schema::hasIndex('interactions', 'interactions_channel_occurred_at_index')
            ) {
                $table->index(['channel', 'occurred_at'], 'interactions_channel_occurred_at_index');
            }

            if (
                Schema::hasColumn('interactions', 'priority')
                && Schema::hasColumn('interactions', 'occurred_at')
                && ! Schema::hasIndex('interactions', 'interactions_priority_occurred_at_index')
            ) {
                $table->index(['priority', 'occurred_at'], 'interactions_priority_occurred_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            if (Schema::hasIndex('interactions', 'interactions_external_id_index')) {
                $table->dropIndex('interactions_external_id_index');
            }

            if (Schema::hasIndex('interactions', 'interactions_channel_occurred_at_index')) {
                $table->dropIndex('interactions_channel_occurred_at_index');
            }

            if (Schema::hasIndex('interactions', 'interactions_priority_occurred_at_index')) {
                $table->dropIndex('interactions_priority_occurred_at_index');
            }
        });

        $columnsToDrop = array_values(array_filter([
            Schema::hasColumn('interactions', 'external_id') ? 'external_id' : null,
            Schema::hasColumn('interactions', 'channel') ? 'channel' : null,
            Schema::hasColumn('interactions', 'direction') ? 'direction' : null,
            Schema::hasColumn('interactions', 'contact_reason') ? 'contact_reason' : null,
            Schema::hasColumn('interactions', 'outcome') ? 'outcome' : null,
            Schema::hasColumn('interactions', 'customer_reference') ? 'customer_reference' : null,
            Schema::hasColumn('interactions', 'queue_name') ? 'queue_name' : null,
            Schema::hasColumn('interactions', 'product_name') ? 'product_name' : null,
            Schema::hasColumn('interactions', 'priority') ? 'priority' : null,
        ]));

        if ($columnsToDrop !== []) {
            Schema::table('interactions', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
