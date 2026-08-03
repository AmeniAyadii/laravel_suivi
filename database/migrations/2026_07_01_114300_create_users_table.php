<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->integer('age');
            $table->enum('sexe', ['M', 'F', 'A']);
            $table->decimal('taille', 5, 2);
            $table->decimal('poids', 5, 2);
            $table->string('groupe_sanguin', 3)->nullable();
            $table->text('allergies')->nullable();
            $table->text('maladies_chroniques')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('medicaments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('nom');
            $table->string('dosage')->nullable();
            $table->string('frequence')->nullable();
            $table->dateTime('prochaine_prise')->nullable();
            $table->text('notes')->nullable();
            $table->enum('statut', ['actif', 'inactif', 'termine'])->default('actif');
            $table->timestamps();
        });

        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('titre');
            $table->string('medecin_nom')->nullable();
            $table->dateTime('date_heure');
            $table->string('lieu')->nullable();
            $table->enum('statut', ['à_venir', 'passé', 'annulé'])->default('à_venir');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('symptomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->text('description');
            $table->integer('niveau')->nullable();
            $table->dateTime('date_enregistrement')->default(now());
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptomes');
        Schema::dropIfExists('rendez_vous');
        Schema::dropIfExists('medicaments');
        Schema::dropIfExists('users');
    }
};
