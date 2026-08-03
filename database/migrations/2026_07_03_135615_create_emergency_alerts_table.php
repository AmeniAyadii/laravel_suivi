<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained()
                  ->onDelete('cascade');

            $table->text('symptomes')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('adresse', 255)->nullable();

            $table->enum('statut', ['envoyee', 'en_cours', 'terminee', 'annulee'])->default('envoyee');
            $table->timestamp('resolue_a')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('statut');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_alerts');
    }
};
