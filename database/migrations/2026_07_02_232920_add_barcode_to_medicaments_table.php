<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('medicaments', function (Blueprint $table) {
            $table->string('barcode')->nullable()->unique()->after('user_id');
            $table->string('code_type')->nullable()->after('barcode');
            $table->string('manufacturer')->nullable()->after('nom');
            $table->string('category')->nullable()->after('manufacturer');
            $table->date('expiry_date')->nullable()->after('notes');
            $table->boolean('scanned')->default(false)->after('statut');
        });
    }

    public function down()
    {
        Schema::table('medicaments', function (Blueprint $table) {
            $table->dropColumn(['barcode', 'code_type', 'manufacturer', 'category', 'expiry_date', 'scanned']);
        });
    }
};