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
        Schema::table('ec_products', function (Blueprint $table) {
            $table->string('wide_unit')->nullable()->after('wide');
            $table->string('height_unit')->nullable()->after('height');
            $table->string('weight_unit')->nullable()->after('weight');
            $table->string('length_unit')->nullable()->after('length');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ec_products', function (Blueprint $table) {
            //
        });
    }
};
