<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('health_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->dateTime('assessment_date')->default(now());
            
            // Paramètres vitaux
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('height', 5, 2)->nullable();
            $table->decimal('bmi', 4, 2)->nullable();
            
            // Pression artérielle
            $table->integer('blood_pressure_systolic')->nullable();
            $table->integer('blood_pressure_diastolic')->nullable();
            
            // Autres mesures
            $table->integer('heart_rate')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->decimal('blood_sugar', 5, 2)->nullable();
            $table->decimal('cholesterol', 5, 2)->nullable();
            
            // Mode de vie
            $table->decimal('sleep_hours', 3, 1)->nullable();
            $table->integer('exercise_minutes')->nullable();
            $table->integer('water_intake')->nullable();
            $table->integer('stress_level')->nullable();
            $table->string('mood')->nullable();
            
            // Résultats
            $table->text('notes')->nullable();
            $table->integer('overall_score')->nullable();
            $table->json('recommendations')->nullable();
            $table->string('status')->default('completed');
            
            $table->timestamps();

            $table->index('user_id');
            $table->index('assessment_date');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('health_assessments');
    }
};