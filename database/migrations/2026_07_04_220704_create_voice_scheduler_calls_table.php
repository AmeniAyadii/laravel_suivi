<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_scheduler_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // ✅ CORRECTION : Utiliser 'rendez_vous' (pas 'rendez_vouses')
            $table->foreignId('rendez_vous_id')->nullable()
                  ->constrained('rendez_vous')  // ← Spécifier le nom exact de la table
                  ->onDelete('set null');
            
            // Informations de l'appel
            $table->string('call_sid')->nullable();
            $table->string('cabinet_phone');
            $table->string('doctor_name');
            $table->dateTime('preferred_date')->nullable();
            $table->string('preferred_time')->nullable();
            
            // Statut
            $table->enum('status', [
                'pending', 'calling', 'answered', 'negotiating',
                'confirmed', 'failed', 'cancelled', 'completed'
            ])->default('pending');
            
            // Résultats
            $table->string('offered_date')->nullable();
            $table->string('offered_time')->nullable();
            $table->text('conversation_log')->nullable();
            $table->text('user_response')->nullable();
            $table->string('recording_url')->nullable();
            
            // Métadonnées
            $table->integer('retry_count')->default(0);
            $table->timestamp('called_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_scheduler_calls');
    }
};