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
        Schema::create('scanned_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('barcode')->unique();
            $table->string('nom');
            $table->string('manufacturer')->nullable();
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('dosage')->nullable();
            $table->string('product_type')->default('unknown');
            $table->string('image_url')->nullable();
            $table->json('ingredients')->nullable();
            $table->json('indications')->nullable();
            $table->json('contre_indications')->nullable();
            $table->json('effets_secondaires')->nullable();
            $table->text('notes')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
            
            // Index pour améliorer les performances
            $table->index('user_id');
            $table->index('barcode');
            $table->index('product_type');
            $table->index('scanned_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scanned_products');
    }
};