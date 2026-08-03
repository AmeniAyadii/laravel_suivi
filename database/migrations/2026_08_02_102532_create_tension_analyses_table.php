<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('tension_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('measure_id')->nullable()->constrained('tension_measures')->onDelete('set null');
            $table->enum('analysis_type', ['single', 'trend', 'prediction'])->default('single');
            $table->text('summary');
            $table->text('recommendation');
            $table->enum('severity_level', ['normal', 'warning', 'danger'])->default('normal');
            $table->json('details')->nullable(); // Stocker les données détaillées de l'analyse
            $table->datetime('analyzed_date');
            $table->timestamps();

            $table->index(['user_id', 'analyzed_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('tension_analyses');
    }
};