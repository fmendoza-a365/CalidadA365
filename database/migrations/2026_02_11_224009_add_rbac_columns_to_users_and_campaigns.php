<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supervisor_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::create('campaign_managers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['campaign_id', 'user_id']); // Prevent duplicates
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_managers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supervisor_id']);
            $table->dropColumn('supervisor_id');
        });
    }
};
