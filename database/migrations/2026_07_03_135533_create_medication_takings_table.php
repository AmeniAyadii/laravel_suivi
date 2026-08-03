<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_takings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicament_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');

            $table->dateTime('prise_prevue');
            $table->dateTime('prise_reelle')->nullable();
            $table->enum('statut', ['prevue', 'prise', 'oubliee', 'reportee'])->default('prevue');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('medicament_id');
            $table->index('statut');
            $table->index('prise_prevue');
            $table->index(['user_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_takings');
    }
};
