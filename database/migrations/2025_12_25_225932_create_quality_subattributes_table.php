<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_subattributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained('quality_attributes')->onDelete('cascade');
            $table->string('name');
            $table->decimal('weight_percent', 5, 2);
            $table->text('concept')->nullable();
            $table->text('guidelines')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            
            $table->index('attribute_id');
            $table->index(['attribute_id', 'sort_order']);
            $table->index('is_critical');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_subattributes');
    }
};
