<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->decimal('target_value', 10, 2);
            $table->boolean('is_completed')->default(false);
            $table->date('completed_date')->nullable();
            $table->timestamps();
            
            $table->index(['goal_id', 'is_completed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_milestones');
    }
};