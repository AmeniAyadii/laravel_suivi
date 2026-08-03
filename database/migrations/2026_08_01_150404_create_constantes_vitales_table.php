<?php

// database/migrations/2026_08_01_create_constantes_vitales_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('constantes_vitales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->enum('type_constante', [
                'tension_systolique',
                'tension_diastolique',
                'frequence_cardiaque',
                'temperature',
                'glycemie',
                'saturation_oxygene',
                'poids',
                'IMC'
            ]);
            $table->decimal('valeur', 8, 2);
            $table->string('unite', 20);
            $table->dateTime('date_mesure');
            $table->text('notes')->nullable();
            $table->boolean('est_anormal')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'date_mesure']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('constantes_vitales');
    }
};
