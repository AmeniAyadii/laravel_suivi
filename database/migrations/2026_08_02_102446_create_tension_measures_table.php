<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tension_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('systolic'); // Pression systolique (mmHg)
            $table->integer('diastolic'); // Pression diastolique (mmHg)
            $table->integer('heart_rate')->nullable(); // Pouls (bpm)
            $table->dateTime('measure_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Index pour les requêtes rapides
            $table->index(['user_id', 'measure_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tension_measures');
    }
};