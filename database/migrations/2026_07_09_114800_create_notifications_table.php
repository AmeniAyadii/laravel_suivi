<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('titre', 255);
            $table->text('message');
            $table->string('type', 50)->default('info');
            $table->string('icon', 50)->nullable();
            $table->string('couleur', 20)->nullable();
            $table->json('data')->nullable();
            $table->boolean('lu')->default(false);
            $table->string('lien', 255)->nullable();
            $table->timestamp('date_envoi')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lu']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
