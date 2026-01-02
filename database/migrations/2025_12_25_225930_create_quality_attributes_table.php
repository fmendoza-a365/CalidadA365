<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained('quality_form_versions')->onDelete('cascade');
            $table->string('name');
            $table->decimal('weight', 5, 2);
            $table->text('concept')->nullable();
            $table->text('guidelines')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('form_version_id');
            $table->index(['form_version_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_attributes');
    }
};
