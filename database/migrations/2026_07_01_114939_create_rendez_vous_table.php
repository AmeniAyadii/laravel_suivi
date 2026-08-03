<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            $table->string('titre', 150);
            $table->string('medecin_nom', 100)->nullable();
            $table->dateTime('date_heure');
            $table->string('lieu', 200)->nullable();
            $table->enum('statut', ['à_venir', 'passé', 'annulé'])->default('à_venir');
            $table->text('notes')->nullable();
            $table->timestamps();

            // Index pour optimiser les recherches
            $table->index('user_id');
            $table->index('statut');
            $table->index('date_heure');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};
