<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('interactions', 'call_sn')) {
            Schema::table('interactions', function (Blueprint $table) {
                $table->string('call_sn', 100)->nullable()->after('file_name');
            });
        }

        if (! Schema::hasIndex('interactions', 'interactions_call_sn_index')) {
            Schema::table('interactions', function (Blueprint $table) {
                $table->index('call_sn', 'interactions_call_sn_index');
            });
        }
    }

    public function down(): void
    {
        Schema::table('interactions', function (Blueprint $table) {
            if (Schema::hasIndex('interactions', 'interactions_call_sn_index')) {
                $table->dropIndex('interactions_call_sn_index');
            }

            if (Schema::hasColumn('interactions', 'call_sn')) {
                $table->dropColumn('call_sn');
            }
        });
    }
};
