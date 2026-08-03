<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
            $table->string('nom', 150);
            $table->string('dosage', 50)->nullable();
            $table->string('frequence', 100)->nullable();
            $table->dateTime('prochaine_prise')->nullable();
            $table->text('notes')->nullable();
            $table->enum('statut', ['actif', 'inactif', 'termine'])->default('actif');
            $table->timestamps();

            // Index pour optimiser les recherches
            $table->index('user_id');
            $table->index('statut');
            $table->index('prochaine_prise');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medicaments');
    }
};
