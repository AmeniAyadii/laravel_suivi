<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('icon');
            $table->string('color_hex')->default('#FFD700');
            $table->enum('type', [
                'medication', 'exercise', 'nutrition', 'sleep', 
                'consistency', 'streak', 'community'
            ]);
            $table->integer('level')->default(1);
            $table->integer('points')->default(0);
            $table->json('requirements')->nullable();
            $table->timestamps();
            
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};