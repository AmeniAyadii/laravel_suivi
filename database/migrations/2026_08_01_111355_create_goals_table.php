<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', [
                'medication', 'exercise', 'nutrition', 'sleep', 
                'hydration', 'weight', 'blood_pressure', 'blood_sugar', 
                'stress', 'other'
            ]);
            $table->decimal('target_value', 10, 2);
            $table->decimal('current_value', 10, 2)->default(0);
            $table->string('unit');
            $table->enum('status', [
                'not_started', 'in_progress', 'completed', 'failed'
            ])->default('not_started');
            $table->date('start_date');
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->integer('progress_percentage')->default(0);
            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern')->nullable();
            $table->json('reminders')->nullable();
            $table->string('icon')->default('🎯');
            $table->string('color_hex')->default('#4CAF50');
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Index pour optimiser les requêtes
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'category']);
            $table->index(['user_id', 'target_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};