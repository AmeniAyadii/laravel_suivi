<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('alertes_sante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type_alerte', [
                'tension_elevee',
                'tension_basse',
                'fievre',
                'hypoglycemie',
                'hyperglycemie',
                'tachycardie',
                'bradycardie',
                'saturation_basse',
                'poids_anormal',
                'personnalise',
                'correlation'
            ]);
            $table->enum('niveau_gravite', ['faible', 'modere', 'eleve', 'critique']);
            $table->text('message');
            $table->foreignId('constante_id')->nullable()->constrained('constantes_vitales')->onDelete('set null');
            $table->boolean('est_lue')->default(false);
            $table->boolean('est_resolue')->default(false);
            $table->dateTime('date_creation');
            $table->dateTime('date_resolution')->nullable();
            $table->text('commentaire_resolution')->nullable();
            
            $table->index(['user_id', 'est_lue', 'est_resolue']);
            $table->index('date_creation');
        });
    }

    public function down()
    {
        Schema::dropIfExists('alertes_sante');
    }
};