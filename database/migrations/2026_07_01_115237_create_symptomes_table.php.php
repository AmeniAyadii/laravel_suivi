<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('symptomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            $table->text('description');
            $table->integer('niveau')->nullable();
            $table->dateTime('date_enregistrement')->default(now());
            $table->text('notes')->nullable();
            $table->timestamps();

            // Index pour optimiser les recherches
            $table->index('user_id');
            $table->index('date_enregistrement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptomes');
    }
};
