<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medicaments', function (Blueprint $table) {
            // ✅ Ajouter les colonnes de stock
            if (!Schema::hasColumn('medicaments', 'stock_actuel')) {
                $table->integer('stock_actuel')->default(0)->after('prochaine_prise');
            }
            
            if (!Schema::hasColumn('medicaments', 'seuil_alerte_stock')) {
                $table->integer('seuil_alerte_stock')->default(5)->after('stock_actuel');
            }
            
            if (!Schema::hasColumn('medicaments', 'unite_stock')) {
                $table->string('unite_stock', 50)->default('comprimé(s)')->after('seuil_alerte_stock');
            }
            
            // ✅ Ajouter les colonnes pour les horaires
            if (!Schema::hasColumn('medicaments', 'horaires_prises')) {
                $table->json('horaires_prises')->nullable()->after('frequence');
            }
            
            if (!Schema::hasColumn('medicaments', 'date_debut')) {
                $table->date('date_debut')->nullable()->after('horaires_prises');
            }
            
            if (!Schema::hasColumn('medicaments', 'date_fin')) {
                $table->date('date_fin')->nullable()->after('date_debut');
            }
        });
    }

    public function down(): void
    {
        Schema::table('medicaments', function (Blueprint $table) {
            $table->dropColumn([
                'stock_actuel', 
                'seuil_alerte_stock', 
                'unite_stock', 
                'horaires_prises', 
                'date_debut', 
                'date_fin'
            ]);
        });
    }
};