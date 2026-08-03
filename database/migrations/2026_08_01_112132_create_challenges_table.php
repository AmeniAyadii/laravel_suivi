<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('icon')->default('🏆');
            $table->string('color_hex')->default('#FF6B6B');
            $table->integer('participants_count')->default(0);
            $table->integer('completion_rate')->default(0);
            $table->date('start_date');
            $table->date('end_date');
            $table->json('rewards')->nullable();
            $table->json('requirements')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['start_date', 'end_date']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};