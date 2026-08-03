<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_reminders', function (Blueprint $table) {
            $table->id();
            
            // ✅ MÉTHODE MANUELLE (plus fiable)
            $table->unsignedBigInteger('rendez_vous_id');
            $table->foreign('rendez_vous_id')
                  ->references('id')
                  ->on('rendez_vous')
                  ->onDelete('cascade');
            
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
            
            $table->timestamp('send_at');
            $table->timestamp('sent_at')->nullable();
            $table->enum('type', ['24h', '1h', 'briefing'])->default('24h');
            $table->text('message')->nullable();
            $table->boolean('is_sent')->default(false);
            
            $table->timestamps();
            
            // Index
            $table->index(['user_id', 'send_at']);
            $table->index(['rendez_vous_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_reminders');
    }
};