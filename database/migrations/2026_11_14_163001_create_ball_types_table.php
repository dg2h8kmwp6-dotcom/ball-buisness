<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ball_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color_hex', 7);
            $table->unsignedSmallInteger('diameter_mm')->default(70);
            $table->unsignedInteger('price_per_ball_cents');
            $table->unsignedInteger('min_quantity')->default(100);
            $table->unsignedInteger('quantity_step')->default(100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ball_types');
    }
};
