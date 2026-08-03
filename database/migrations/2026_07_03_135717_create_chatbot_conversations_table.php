<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');
            
            $table->string('session_id')->unique();
            $table->string('titre')->nullable();
            $table->integer('nombre_messages')->default(0);
            $table->enum('statut', ['active', 'terminee', 'expiree'])->default('active');
            
            $table->timestamp('derniere_activite')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('session_id');
            $table->index('statut');
            $table->index('derniere_activite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_conversations');
    }
};