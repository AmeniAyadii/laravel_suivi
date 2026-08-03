<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('seuils_personnalises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type_constante');
            $table->decimal('min_normal', 8, 2)->nullable();
            $table->decimal('max_normal', 8, 2)->nullable();
            $table->decimal('min_alerte', 8, 2)->nullable();
            $table->decimal('max_alerte', 8, 2)->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'type_constante']);
            $table->index('user_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('seuils_personnalises');
    }
};