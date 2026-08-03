<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('medicaments', function (Blueprint $table) {
            // Ajouter la colonne actif si elle n'existe pas
            if (!Schema::hasColumn('medicaments', 'actif')) {
                $table->boolean('actif')->default(true)->after('nom');
            }
        });
    }

    public function down()
    {
        Schema::table('medicaments', function (Blueprint $table) {
            if (Schema::hasColumn('medicaments', 'actif')) {
                $table->dropColumn('actif');
            }
        });
    }
};