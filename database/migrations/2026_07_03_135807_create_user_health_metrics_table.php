<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_health_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            $table->date('date_mesure');
            
            // Poids et taille
            $table->decimal('poids', 5, 2)->nullable();
            $table->decimal('taille', 5, 2)->nullable();
            $table->decimal('imc', 4, 2)->nullable();
            
            // Pression artérielle
            $table->integer('pression_systolique')->nullable();
            $table->integer('pression_diastolique')->nullable();
            
            // Autres
            $table->integer('frequence_cardiaque')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->decimal('glycemie', 5, 2)->nullable();
            $table->decimal('cholesterol', 5, 2)->nullable();
            
            // Mode de vie
            $table->decimal('heures_sommeil', 3, 1)->nullable();
            $table->integer('minutes_exercice')->nullable();
            $table->integer('stress_niveau')->nullable();
            
            // Notes
            $table->text('notes')->nullable();
            
            $table->timestamps();

            $table->index('user_id');
            $table->index('date_mesure');
            $table->index(['user_id', 'date_mesure']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_health_metrics');
    }
};