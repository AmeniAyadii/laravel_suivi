<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Notifications
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('medication_reminders')->default(true);
            $table->boolean('appointment_reminders')->default(true);
            $table->boolean('health_tips_enabled')->default(true);

            // Données
            $table->boolean('data_sync_enabled')->default(true);

            // Sécurité
            $table->boolean('biometric_enabled')->default(false);

            // Préférences
            $table->string('language')->default('Français');
            $table->string('theme')->default('Système');
            $table->string('font_size')->default('Moyen');
            $table->float('font_size_value')->default(1.0);

            $table->timestamps();

            // Index
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_settings');
    }
};