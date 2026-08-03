// database/migrations/2026_07_03_135221_add_missing_columns_to_existing_tables.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ============================================
        // 1. TABLE users - Ajout des colonnes manquantes
        // ============================================
        Schema::table('users', function (Blueprint $table) {
            // Informations personnelles
            if (!Schema::hasColumn('users', 'prenom')) {
                $table->string('prenom', 100)->nullable()->after('nom');
            }

            if (!Schema::hasColumn('users', 'date_naissance')) {
                $table->date('date_naissance')->nullable()->after('age');
            }

            // Contact
            if (!Schema::hasColumn('users', 'telephone')) {
                $table->string('telephone', 20)->nullable()->after('email');
            }

            if (!Schema::hasColumn('users', 'adresse')) {
                $table->text('adresse')->nullable()->after('telephone');
            }

            if (!Schema::hasColumn('users', 'code_postal')) {
                $table->string('code_postal', 10)->nullable()->after('adresse');
            }

            if (!Schema::hasColumn('users', 'ville')) {
                $table->string('ville', 100)->nullable()->after('code_postal');
            }

            // Authentification
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('password');
            }

            if (!Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken()->after('email_verified_at');
            }

            // Informations médicales
            if (!Schema::hasColumn('users', 'antecedents_medicaux')) {
                $table->text('antecedents_medicaux')->nullable()->after('maladies_chroniques');
            }

            if (!Schema::hasColumn('users', 'traitements_en_cours')) {
                $table->text('traitements_en_cours')->nullable()->after('antecedents_medicaux');
            }

            if (!Schema::hasColumn('users', 'medecin_traitant_id')) {
                $table->foreignId('medecin_traitant_id')->nullable()->after('traitements_en_cours')
                      ->constrained('users')->nullOnDelete();
            }

            // Contact d'urgence
            if (!Schema::hasColumn('users', 'contact_urgence_nom')) {
                $table->string('contact_urgence_nom', 100)->nullable()->after('medecin_traitant_id');
            }

            if (!Schema::hasColumn('users', 'contact_urgence_telephone')) {
                $table->string('contact_urgence_telephone', 20)->nullable()->after('contact_urgence_nom');
            }

            if (!Schema::hasColumn('users', 'contact_urgence_relation')) {
                $table->string('contact_urgence_relation', 50)->nullable()->after('contact_urgence_telephone');
            }

            // Préférences
            if (!Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable()->after('contact_urgence_relation');
            }

            // Statut
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['patient', 'medecin', 'admin'])->default('patient')->after('preferences');
            }

            if (!Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('role');
            }

            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('is_active');
            }

            // Soft delete
            if (!Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // Ajouter les index sur users (après avoir ajouté les colonnes)
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role') && !Schema::hasIndex('users', 'users_role_index')) {
                $table->index('role');
            }
            if (Schema::hasColumn('users', 'is_active') && !Schema::hasIndex('users', 'users_is_active_index')) {
                $table->index('is_active');
            }
            if (!Schema::hasIndex('users', 'users_nom_prenom_index')) {
                $table->index(['nom', 'prenom']);
            }
        });


        // ============================================
        // 2. TABLE medicaments - Ajout des colonnes manquantes
        // ============================================
        Schema::table('medicaments', function (Blueprint $table) {
            // Informations du médicament
            if (!Schema::hasColumn('medicaments', 'nom_generique')) {
                $table->string('nom_generique', 150)->nullable()->after('nom');
            }

            if (!Schema::hasColumn('medicaments', 'forme')) {
                $table->string('forme', 50)->nullable()->after('dosage');
            }

            if (!Schema::hasColumn('medicaments', 'voie_administration')) {
                $table->string('voie_administration', 50)->nullable()->after('forme');
            }

            if (!Schema::hasColumn('medicaments', 'laboratoire')) {
                $table->string('laboratoire', 100)->nullable()->after('voie_administration');
            }

            // Posologie
            if (!Schema::hasColumn('medicaments', 'horaires_prises')) {
                $table->json('horaires_prises')->nullable()->after('frequence');
            }

            if (!Schema::hasColumn('medicaments', 'quantite_par_prise')) {
                $table->string('quantite_par_prise', 50)->nullable()->after('horaires_prises');
            }

            if (!Schema::hasColumn('medicaments', 'duree_traitement_jours')) {
                $table->integer('duree_traitement_jours')->nullable()->after('quantite_par_prise');
            }

            // Dates
            if (!Schema::hasColumn('medicaments', 'date_debut')) {
                $table->date('date_debut')->nullable()->after('duree_traitement_jours');
            }

            if (!Schema::hasColumn('medicaments', 'date_fin')) {
                $table->date('date_fin')->nullable()->after('date_debut');
            }

            // Stock
            if (!Schema::hasColumn('medicaments', 'stock_actuel')) {
                $table->integer('stock_actuel')->default(0)->after('prochaine_prise');
            }

            if (!Schema::hasColumn('medicaments', 'seuil_alerte_stock')) {
                $table->integer('seuil_alerte_stock')->default(5)->after('stock_actuel');
            }

            if (!Schema::hasColumn('medicaments', 'unite_stock')) {
                $table->string('unite_stock', 50)->default('comprimé(s)')->after('seuil_alerte_stock');
            }

            // Informations complémentaires
            if (!Schema::hasColumn('medicaments', 'instructions')) {
                $table->text('instructions')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('medicaments', 'effets_secondaires')) {
                $table->text('effets_secondaires')->nullable()->after('instructions');
            }

            if (!Schema::hasColumn('medicaments', 'contre_indications')) {
                $table->text('contre_indications')->nullable()->after('effets_secondaires');
            }

            if (!Schema::hasColumn('medicaments', 'interactions')) {
                $table->text('interactions')->nullable()->after('contre_indications');
            }

            // Prescription
            if (!Schema::hasColumn('medicaments', 'prescrit_par')) {
                $table->string('prescrit_par', 100)->nullable()->after('interactions');
            }

            if (!Schema::hasColumn('medicaments', 'date_prescription')) {
                $table->date('date_prescription')->nullable()->after('prescrit_par');
            }

            // Rappel
            if (!Schema::hasColumn('medicaments', 'rappel_actif')) {
                $table->boolean('rappel_actif')->default(true)->after('statut');
            }

            // Soft delete
            if (!Schema::hasColumn('medicaments', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // Ajouter les index sur medicaments (vérifier que les colonnes existent)
        Schema::table('medicaments', function (Blueprint $table) {
            if (Schema::hasColumn('medicaments', 'statut') && !Schema::hasIndex('medicaments', 'medicaments_statut_index')) {
                $table->index('statut');
            }
            if (Schema::hasColumn('medicaments', 'prochaine_prise') && !Schema::hasIndex('medicaments', 'medicaments_prochaine_prise_index')) {
                $table->index('prochaine_prise');
            }
            if (!Schema::hasIndex('medicaments', 'medicaments_user_id_statut_index')) {
                $table->index(['user_id', 'statut']);
            }
            if (!Schema::hasIndex('medicaments', 'medicaments_nom_index')) {
                $table->index('nom');
            }
        });


        // ============================================
        // 3. TABLE rendez_vous - Ajout des colonnes manquantes
        // ============================================
        Schema::table('rendez_vous', function (Blueprint $table) {
            // Informations du médecin
            if (!Schema::hasColumn('rendez_vous', 'medecin_specialite')) {
                $table->string('medecin_specialite', 100)->nullable()->after('medecin_nom');
            }

            if (!Schema::hasColumn('rendez_vous', 'medecin_telephone')) {
                $table->string('medecin_telephone', 20)->nullable()->after('medecin_specialite');
            }

            if (!Schema::hasColumn('rendez_vous', 'medecin_email')) {
                $table->string('medecin_email', 100)->nullable()->after('medecin_telephone');
            }

            // Date et lieu
            if (!Schema::hasColumn('rendez_vous', 'date_fin')) {
                $table->dateTime('date_fin')->nullable()->after('date_heure');
            }

            if (!Schema::hasColumn('rendez_vous', 'adresse')) {
                $table->string('adresse', 255)->nullable()->after('lieu');
            }

            if (!Schema::hasColumn('rendez_vous', 'code_postal')) {
                $table->string('code_postal', 10)->nullable()->after('adresse');
            }

            if (!Schema::hasColumn('rendez_vous', 'ville')) {
                $table->string('ville', 100)->nullable()->after('code_postal');
            }

            // Type
            if (!Schema::hasColumn('rendez_vous', 'type')) {
                $table->enum('type', ['presentiel', 'visio', 'telephone'])->default('presentiel')->after('ville');
            }

            if (!Schema::hasColumn('rendez_vous', 'lien_visio')) {
                $table->string('lien_visio')->nullable()->after('type');
            }

            // Informations complémentaires
            if (!Schema::hasColumn('rendez_vous', 'motif')) {
                $table->text('motif')->nullable()->after('titre');
            }

            if (!Schema::hasColumn('rendez_vous', 'notes_medecin')) {
                $table->text('notes_medecin')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('rendez_vous', 'diagnostic')) {
                $table->text('diagnostic')->nullable()->after('notes_medecin');
            }

            if (!Schema::hasColumn('rendez_vous', 'prescriptions')) {
                $table->text('prescriptions')->nullable()->after('diagnostic');
            }

            // Rappel
            if (!Schema::hasColumn('rendez_vous', 'rappel_envoye')) {
                $table->boolean('rappel_envoye')->default(false)->after('prescriptions');
            }

            if (!Schema::hasColumn('rendez_vous', 'rappel_envoye_a')) {
                $table->timestamp('rappel_envoye_a')->nullable()->after('rappel_envoye');
            }

            // Soft delete
            if (!Schema::hasColumn('rendez_vous', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // ✅ MODIFICATION DU STATUT - AVEC VÉRIFICATION
        Schema::table('rendez_vous', function (Blueprint $table) {
            if (Schema::hasColumn('rendez_vous', 'statut')) {
                // Modifier l'enum pour ajouter plus de valeurs
                $table->enum('statut', ['à_venir', 'confirmé', 'en_cours', 'passé', 'annulé', 'reporté'])
                      ->default('à_venir')
                      ->change();
            }
        });

        // Ajouter les index sur rendez_vous
        Schema::table('rendez_vous', function (Blueprint $table) {
            if (!Schema::hasIndex('rendez_vous', 'rendez_vous_user_id_date_heure_index')) {
                $table->index(['user_id', 'date_heure']);
            }
            if (!Schema::hasIndex('rendez_vous', 'rendez_vous_user_id_statut_index')) {
                $table->index(['user_id', 'statut']);
            }
            if (!Schema::hasIndex('rendez_vous', 'rendez_vous_date_heure_index')) {
                $table->index('date_heure');
            }
        });


        // ============================================
        // 4. TABLE symptomes - Ajout des colonnes manquantes
        // ============================================
        Schema::table('symptomes', function (Blueprint $table) {
            // Catégorie et localisation
            if (!Schema::hasColumn('symptomes', 'categorie')) {
                $table->string('categorie', 50)->nullable()->after('description');
            }

            if (!Schema::hasColumn('symptomes', 'partie_corps')) {
                $table->string('partie_corps', 50)->nullable()->after('categorie');
            }

            // Dates
            if (!Schema::hasColumn('symptomes', 'date_debut')) {
                $table->dateTime('date_debut')->nullable()->after('partie_corps');
            }

            if (!Schema::hasColumn('symptomes', 'date_resolution')) {
                $table->dateTime('date_resolution')->nullable()->after('date_enregistrement');
            }

            // Contexte
            if (!Schema::hasColumn('symptomes', 'facteurs_declenchants')) {
                $table->text('facteurs_declenchants')->nullable()->after('date_resolution');
            }

            if (!Schema::hasColumn('symptomes', 'facteurs_soulageants')) {
                $table->text('facteurs_soulageants')->nullable()->after('facteurs_declenchants');
            }

            if (!Schema::hasColumn('symptomes', 'symptomes_associes')) {
                $table->json('symptomes_associes')->nullable()->after('facteurs_soulageants');
            }

            // ✅ AJOUT DE LA COLONNE GRAVITE
            if (!Schema::hasColumn('symptomes', 'gravite')) {
                $table->enum('gravite', ['faible', 'moderee', 'elevee', 'critique'])
                      ->default('faible')
                      ->after('niveau');
            }

            // ✅ AJOUT DE LA COLONNE STATUT SI ELLE N'EXISTE PAS
            if (!Schema::hasColumn('symptomes', 'statut')) {
                $table->enum('statut', ['actif', 'en_amelioration', 'resolu', 'chronique'])
                      ->default('actif')
                      ->after('gravite');
            }

            // Soft delete
            if (!Schema::hasColumn('symptomes', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // Ajouter les index sur symptomes (✅ VÉRIFIER QUE LES COLONNES EXISTENT)
        Schema::table('symptomes', function (Blueprint $table) {
            if (!Schema::hasIndex('symptomes', 'symptomes_user_id_date_enregistrement_index')) {
                $table->index(['user_id', 'date_enregistrement']);
            }

            // ✅ VÉRIFIER QUE LA COLONNE GRAVITE EXISTE AVANT D'AJOUTER L'INDEX
            if (Schema::hasColumn('symptomes', 'gravite') && !Schema::hasIndex('symptomes', 'symptomes_gravite_index')) {
                $table->index('gravite');
            }

            // ✅ VÉRIFIER QUE LA COLONNE STATUT EXISTE AVANT D'AJOUTER L'INDEX
            if (Schema::hasColumn('symptomes', 'statut') && !Schema::hasIndex('symptomes', 'symptomes_statut_index')) {
                $table->index('statut');
            }
        });


        // ============================================
        // 5. TABLE health_assessments - Ajout des colonnes manquantes
        // ============================================
        Schema::table('health_assessments', function (Blueprint $table) {
            // Vérifier si user_id existe
            if (!Schema::hasColumn('health_assessments', 'user_id')) {
                $table->foreignId('user_id')->after('id')->constrained()->onDelete('cascade');
            }

            if (!Schema::hasColumn('health_assessments', 'assessment_date')) {
                $table->dateTime('assessment_date')->default(now())->after('user_id');
            }

            // Paramètres vitaux
            if (!Schema::hasColumn('health_assessments', 'weight')) {
                $table->decimal('weight', 5, 2)->nullable()->after('assessment_date');
            }

            if (!Schema::hasColumn('health_assessments', 'height')) {
                $table->decimal('height', 5, 2)->nullable()->after('weight');
            }

            if (!Schema::hasColumn('health_assessments', 'bmi')) {
                $table->decimal('bmi', 4, 2)->nullable()->after('height');
            }

            // Pression artérielle
            if (!Schema::hasColumn('health_assessments', 'blood_pressure_systolic')) {
                $table->integer('blood_pressure_systolic')->nullable()->after('bmi');
            }

            if (!Schema::hasColumn('health_assessments', 'blood_pressure_diastolic')) {
                $table->integer('blood_pressure_diastolic')->nullable()->after('blood_pressure_systolic');
            }

            // Autres mesures
            if (!Schema::hasColumn('health_assessments', 'heart_rate')) {
                $table->integer('heart_rate')->nullable()->after('blood_pressure_diastolic');
            }

            if (!Schema::hasColumn('health_assessments', 'temperature')) {
                $table->decimal('temperature', 4, 1)->nullable()->after('heart_rate');
            }

            if (!Schema::hasColumn('health_assessments', 'blood_sugar')) {
                $table->decimal('blood_sugar', 5, 2)->nullable()->after('temperature');
            }

            if (!Schema::hasColumn('health_assessments', 'cholesterol')) {
                $table->decimal('cholesterol', 5, 2)->nullable()->after('blood_sugar');
            }

            // Mode de vie
            if (!Schema::hasColumn('health_assessments', 'sleep_hours')) {
                $table->decimal('sleep_hours', 3, 1)->nullable()->after('cholesterol');
            }

            if (!Schema::hasColumn('health_assessments', 'exercise_minutes')) {
                $table->integer('exercise_minutes')->nullable()->after('sleep_hours');
            }

            if (!Schema::hasColumn('health_assessments', 'water_intake')) {
                $table->integer('water_intake')->nullable()->after('exercise_minutes');
            }

            if (!Schema::hasColumn('health_assessments', 'stress_level')) {
                $table->integer('stress_level')->nullable()->after('water_intake');
            }

            if (!Schema::hasColumn('health_assessments', 'mood')) {
                $table->string('mood', 50)->nullable()->after('stress_level');
            }

            // Résultats
            if (!Schema::hasColumn('health_assessments', 'overall_score')) {
                $table->integer('overall_score')->nullable()->after('notes');
            }

            if (!Schema::hasColumn('health_assessments', 'recommendations')) {
                $table->json('recommendations')->nullable()->after('overall_score');
            }

            if (!Schema::hasColumn('health_assessments', 'status')) {
                $table->string('status', 50)->default('completed')->after('recommendations');
            }

            // Soft delete
            if (!Schema::hasColumn('health_assessments', 'deleted_at')) {
                $table->softDeletes()->after('updated_at');
            }
        });

        // Ajouter les index sur health_assessments
        Schema::table('health_assessments', function (Blueprint $table) {
            if (Schema::hasColumn('health_assessments', 'user_id') && !Schema::hasIndex('health_assessments', 'health_assessments_user_id_index')) {
                $table->index('user_id');
            }
            if (Schema::hasColumn('health_assessments', 'assessment_date') && !Schema::hasIndex('health_assessments', 'health_assessments_assessment_date_index')) {
                $table->index('assessment_date');
            }
            if (Schema::hasColumn('health_assessments', 'status') && !Schema::hasIndex('health_assessments', 'health_assessments_status_index')) {
                $table->index('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // ⚠️ ATTENTION: Cette méthode est risquée, utilisez avec précaution
        // Nous ne supprimons pas les colonnes pour éviter la perte de données

        // Si vous voulez vraiment supprimer les colonnes, décommentez le code ci-dessous
        /*
        Schema::table('users', function (Blueprint $table) {
            $columns = ['prenom', 'date_naissance', 'telephone', 'adresse', 'code_postal', 'ville',
                       'email_verified_at', 'remember_token', 'antecedents_medicaux', 'traitements_en_cours',
                       'medecin_traitant_id', 'contact_urgence_nom', 'contact_urgence_telephone',
                       'contact_urgence_relation', 'preferences', 'role', 'is_active', 'last_login_at',
                       'deleted_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        */
    }
};
